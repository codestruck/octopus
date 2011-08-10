<?php

define('OCTOPUS_MIGRATIONS_TABLE', '_octopus_migrations');

// For migrations that are prefixed with one or more '_', their version # is
// added to this internally so that they are run first.
define('OCTOPUS_MIGRATIONS_RUN_FIRST_OFFSET', -9999999);

Octopus::loadClass('Octopus_DB_Select');

class Octopus_DB_Migration_Runner {

    private $dirs;
    private $db;
    private $options;

    public function __construct($dirs, $db = null, $options = array()) {

        $this->dirs = is_array($dirs) ? $dirs : array($dirs);
        $this->db = ($db === null ? Octopus_DB::singleton() : $db);

        if (!isset($options['ROOT_DIR'])) {
            if (class_exists('Octopus_App') && Octopus_App::isStarted()) {
                $app = Octopus_App::singleton();
                $options['ROOT_DIR'] = $app->getOption('ROOT_DIR');
            } else if (defined('ROOT_DIR')) {
                $options['ROOT_DIR'] = ROOT_DIR;
            } else {
                $options['ROOT_DIR'] = '';
            }
        }

        $this->options = $options;
    }

    /**
     * @param $toVersion Mixed Version being migrated to. If not specified, the
     * latest version is used.
     * @param $fromVersion Mixed Version from which the migration is running.
     * if not specified, the last version applied is used.
     * @return Array of migration instances to be run in the order they should
     * be run.
     */
    public function getMigrations($fromVersion = null, $toVersion = null) {

        $this->figureOutVersionStuff($fromVersion, $toVersion, $minVersion, $maxVersion);

        $versions = $this->getMigrationVersions($minVersion, $maxVersion);

        $result = array();

        foreach($versions as $version) {
            $result[] = $this->createMigration($version);
        }

        if ($this->compareMigrationVersions($fromVersion, $toVersion) < 0) {
            return array_reverse($result);
        }

        return $result;
    }

    /**
     * Given a version array, create an actual migration instance.
     */
    protected function createMigration($version) {

        self::__requireOnce($version['file']);

        $classNames = self::getPotentialClassNames($version['file']);

        $migration = null;
        foreach($classNames as $class) {

            if (class_exists($class)) {
                $migration = new $class();
            }

        }

        if (!$migration) {
            $tried = implode(', ', $classNames);
            throw new Octopus_Exception("No migration class found in file {$version['file']} (tried $tried)");
        }

        return $migration;
    }

    private function getVersionByIndex($index) {

        $versions = $this->getMigrationVersions();
        return isset($versions[$index - 1]) ? $versions[$index - 1] : null;

    }

    private function getVersionByHash($hash) {

        $versions = $this->getMigrationVersions();
        foreach($versions as $v) {
            if ($v['hash'] === $hash) {
                return $v;
            }
        }

        return null;
    }

    private function figureOutVersionStuff($fromVersion, $toVersion, &$minVersion, &$maxVersion) {

        if ($fromVersion === null) {
            $fromVersion = $this->getCurrentVersion();
        } else if (is_numeric($fromVersion)) {
            $index = $fromVersion;
            $fromVersion = $this->getVersionByIndex($index);
            if ($fromVersion === null) {
                throw new Octopus_Exception("Invalid version index: $index");
            }
        } else if (is_string($fromVersion) && strlen($fromVersion) === 40) {
            $hash = $fromVersion;
            $fromVersion = $this->getVersionByHash($hash);
            if ($fromVersion === null) {
                throw new Octopus_Exception("Invalid version hash: $hash");
            }
        }

        if ($toVersion === null) {
            $toVersion = $this->getLatestVersion();
        } else if (is_numeric($toVersion)) {
            $index = $toVersion;
            $toVersion = $this->getVersionByIndex($index);
            if ($toVersion === null) {
                throw new Octopus_Exception("Invalid version index: $index");
            }
        } else if (is_string($toVersion) && strlen($toVersion) === 40) {
            $hash = $toVersion;
            $toVersion = $this->getVersionByHash($hash);
            if ($toVersion === null) {
                throw new Octopus_Exception("Invalid version hash: $hash");
            }
        }

        $comp = $this->compareMigrationVersions($fromVersion, $toVersion);

        if ($comp <= 0) {
            $minVersion = $fromVersion;
            $maxVersion = $toVersion;
        } else {
            $minVersion = $toVersion;
            $maxVersion = $fromVersion;
        }
    }

    /**
     * @return Array the latest applied version, or null if nothing has been
     * applied yet.
     */
    public function getCurrentVersion() {

        if (!$this->checkMigrationsTable()) {
            // no table = no migrations!
            return null;
        }

        $s = new Octopus_DB_Select();
        $s->table(OCTOPUS_MIGRATIONS_TABLE);
        $versions = $s->fetchAll();

        if (empty($versions)) {
            return null;
        }

        usort($versions, array($this, 'compareMigrationVersions'));

        return array_pop($versions);
    }

    /**
     * @return Mixed Either an Array describing the highest possible version
     * or null if no versions are available.
     */
    public function getLatestVersion() {
        $versions = $this->getMigrationVersions();
        return $versions ? array_pop($versions) : null;
    }

    /**
     * @return Array of available migration versions, sorted in ascending
     * order.
     */
    protected function getMigrationVersions($minVersion = null, $maxVersion = null) {

        $versions = array();

        foreach($this->dirs as $dir) {

            $files = glob(rtrim($dir, '/') . '/*.php');
            if ($files) {
                foreach($files as $file) {
                    $versions[] = $this->getMigrationVersion($file);
                }
            }

        }

        usort($versions, array($this, 'compareMigrationVersions'));

        if ($minVersion === null && $maxVersion === null) {
            return $versions;
        } else if ($minVersion === null && $maxVersion === 0) {
            $minVersion = 0;
            $maxVersion = null;
        }

        $result = array();
        foreach($versions as $version) {

            if (($minVersion === null || ($this->compareMigrationVersions($minVersion, $version) <= 0)) &&
                ($maxVersion === null || ($this->compareMigrationVersions($maxVersion, $version) >= 0))) {
                $result[] = $version;
            }

        }

        return $result;
    }

    /**
     * Checks that the migrations table exists, optionally creating it.
     */
    protected function checkMigrationsTable($create = false) {

        $schema = new Octopus_DB_Schema($this->db);
        if ($schema->checkTable(OCTOPUS_MIGRATIONS_TABLE)) {
            return true;
        }

        if ($create) {
            $this->createMigrationsTable();
        }

        return false;
    }

    protected function createMigrationsTable() {

        $t = new Octopus_DB_Schema_Writer(OCTOPUS_MIGRATIONS_TABLE, $this->db);
        $t->newTextSmall('hash', 40);
        $t->newTextSmall('set', 100);
        $t->newTextSmall('name', 100);
        $t->newInt('number');
        $t->newTextSmall('file', 250); // Only for future reference, not actually used
        $t->newPrimaryKey('hash');
        $t->create();

    }

    /**
     * @return Array of migration versions that have been applied.
     */
    public function getAppliedMigrations() {

        if (!$this->checkMigrationsTable()) {
            return array();
        }

        $s = new Octopus_DB_Select();
        $s->table(OCTOPUS_MIGRATIONS_TABLE);
        $versions = $s->fetchAll();

        usort($versions, array($this, 'compareMigrationVersions'));

        return $versions;

    }

    /**
     * @return Array version information.
     */
    protected function getMigrationVersion($file) {

        $set = $this->getMigrationSet($file);
        if (!$set) throw new Octopus_Exception('Invalid migration file: ' . $file);

        $name = basename($file, '.php');

        $number = 0;

        if (preg_match('/^(_*)(\d+)/', $name, $m)) {

            // Prefixing w/ 1 or more '_' characters means it gets a much
            // higher priority.

            $number = $m[2];
            if ($m[1]) {
                $number = OCTOPUS_MIGRATIONS_RUN_FIRST_OFFSET + $number;
            }
        }

        $name = preg_replace('/^_*\d+_*/', '', $name);
        $name = strtolower($name);

        $hash = sha1($set . '|' . $name . '|' . $number);

        return compact('hash', 'set', 'name', 'number', 'file');
    }

    /**
     * @return An identifier for the chunk of migrations the given file is
     * a part of.
     */
    protected function getMigrationSet($file) {

        $dir = trim(dirname($file), '/');
        $rootDir = trim($this->options['ROOT_DIR'], '/');

        $dirLen = strlen($dir);
        $rootDirLen = strlen($rootDir);

        if ($rootDirLen > $dirLen) {
            return $dir;
        }

        if (strncasecmp($dir, $rootDir, $rootDirLen) === 0) {
            return trim(substr($dir, $rootDirLen), '/');
        }

        return $dir;
    }

    private static function __requireOnce($file) {
        require_once($file);
    }

    /**
     * @return Array of files containing migration classes, in ascending order.
     */
    protected function getMigrationFiles() {

        $result = array();

        foreach($this->dirs as $dir) {

            if (!is_dir($dir)) {
                continue;
            }

            $dir = rtrim($dir, '/') . '/';
            $files = glob($dir . '*.php');

            if (!$files) {
                continue;
            }

            usort($files, array($this, 'compareMigrationFiles'));

            foreach($files as $file) {
                $result[$file] = self::getPotentialClassNames($file);
            }
        }

        return $result;
    }

    private static function getPotentialClassNames($file) {

        $result = array();
        $name = basename($file, '.php');
        $name = preg_replace('/(^_*(\d+)_*|_*Migration$)/i', '', $name);

        $name = camel_case($name, true) . 'Migration';

        $result[$name] = true;
        $result[underscore($name)] = true;

        return array_keys($result);
    }

    private function compareMigrationVersions($x, $y) {

        if ($x === 0 && $y !== 0) {
            return -1;
        } else if ($x !== 0 && $y === 0) {
            return 1;
        } else if ($x === 0 && $y === 0) {
            return 0;
        }

        $result = $x['number'] - $y['number'];
        if ($result !== 0) return $result;

        return $this->compareMigrationSets($x['set'], $y['set']);

    }

    private function compareMigrationFiles($x, $y) {

        $xVersion = $this->getMigrationVersion($x);
        $yVersion = $this->getMigrationVersion($y);

        return $this->compareMigrationVersions($xVersion, $yVersion);
    }

    private function compareMigrationSets($x, $y) {
        return strcasecmp($x, $y);
    }

    public function migrate($toVersion = null, $fromVersion = null) {

        $this->createMigrationsTable();

        $versions = $this->getMigrationVersions($fromVersion, $toVersion);

        if ($toVersion === 0) {
            $up = false;
        } else {
            $up = ($this->compareMigrationVersions($fromVersion, $toVersion) >= 0);
        }

        return $this->runMigrations($versions, $up, true);
    }

    private function runMigrations($versions, $up, $undoOnException) {

        $run = array();

        try {

            foreach($versions as $version) {

                if ($up) {
                    $this->applyVersion($version);
                } else {
                    $this->unapplyVersion($version);
                }

                $run[] = $version;

            }

        } catch (Exception $ex) {

            if ($undoOnException) {
                $this->runMigrations(array_reverse($run), !$up, false);
            }

            throw $ex;
        }

    }

    /**
     * Runs the 'up' migration for the given version if it has not
     * already been run.
     */
    protected function applyVersion($version) {

        if ($this->versionHasBeenApplied($version)) {
            return;
        }

        $migration = $this->createMigration($version);

        if (method_exists($migration, 'up')) {
            $migration->up();
        }

        $i = new Octopus_DB_Insert();
        $i->table(OCTOPUS_MIGRATIONS_TABLE);
        foreach($version as $key => $value) {
            $i->set($key, $value);
        }
        $i->execute();
    }

    /**
     * Removes a version if it has been applied.
     */
    protected function unapplyVersion($version) {

        if (!$this->versionHasBeenApplied($version)) {
            return;
        }

        $migration = $this->createMigration($version);

        if (method_exists($migration, 'down')) {
            $migration->down();
        }

        $d = new Octopus_DB_Delete();
        $d->table(OCTOPUS_MIGRATIONS_TABLE);
        $d->where('hash = ?', $version['hash']);
        $d->execute();
    }

    protected function versionHasBeenApplied($version) {

        $s = new Octopus_DB_Select();
        $s->table(OCTOPUS_MIGRATIONS_TABLE, 'hash');
        $s->where('hash = ?', $version['hash']);

        return !!$s->getOne();

    }

}

?>
