<?php

define('OCTOPUS_MIGRATIONS_TABLE', '_migrations');

// For migrations that are prefixed with one or more '_', their version # is
// added to this internally so that they are run first.
define('OCTOPUS_MIGRATIONS_RUN_FIRST_OFFSET', -1000000);

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
     * @return Mixed Either an Array describing the highest possible version
     * or null if no versions are available.
     */
    public function getLatestVersion() {
        $versions = $this->getAllVersions();
        return $versions ? array_pop($versions) : null;
    }

    /**
     * @return Array of available migration versions, sorted in the order they
     * should be processed.
     */
    public function &getMigrationVersions($from = null, $to = null, &$up = null) {

        $versions = $this->getAllVersions();

        $this->figureOutVersionStuff($from, $to, $minVersion, $maxVersion, $up);

        $result = array();

        foreach($versions as $v) {

            if ($up) {

                // Moving up == include all versions <= $maxVersion
                if ($this->compareMigrationVersions($v, $maxVersion) <= 0) {
                    $result[] = $v;
                }

            } else {

                // Moving down == include all versions >= $minVersion
                if ($this->compareMigrationVersions($v, $minVersion) >= 0) {
                    $result[] = $v;
                }

            }
        }

        if (!$up) $result = array_reverse($result);

        return $result;
    }

    /**
     * @param $toVersion Mixed Version being migrated to. If not specified, the
     * latest version is used.
     * @param $fromVersion Mixed Version from which the migration is running.
     * if not specified, the last version applied is used.
     * @param $up Boolean Gets set to true if the 'up' method should be used,
     * false if the 'down' method should be used.
     * @return Array of migration instances to be run in the order they should
     * be run.
     */
    public function getMigrations($fromVersion = null, $toVersion = null, &$up = null) {

        $versions = $this->getMigrationVersions($fromVersion, $toVersion, $up);
        $result = array();

        foreach($versions as $version) {
            $result[] = $this->createMigration($version);
        }

        return $result;
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
     * @return Boolean Whether or not there are any migrations that need to
     * be applied.
     */
    public function haveUnappliedMigrations() {

        $current = $this->getCurrentVersion();
        $latest = $this->getLatestVersion();

        return $this->compareMigrationVersions($current, $latest) < 0;
    }

    /**
     * Runs migrations :-)
     */
    public function migrate($toVersion = null, $fromVersion = null) {

        $this->createMigrationsTable();

        $versions = $this->getMigrationVersions($fromVersion, $toVersion, $up);

        return $this->runMigrations($versions, $up, true);
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
        $t->newTextSmall('name', 100);
        $t->newInt('number');
        $t->newTextSmall('file', 250); // Only for future reference, not actually used
        $t->newPrimaryKey('hash');
        $t->create();

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



    /**
     * @return Array of versions sorted in ascending order.
     */
    private function &getAllVersions() {

        $versions = array();

        foreach($this->dirs as $dir) {

            $files = glob(rtrim($dir, '/') . '/*.php');
            if ($files) {
                foreach($files as $file) {
                    $versions[] = $this->getMigrationVersion($file);
                }
            }

        }

        // Sort versions in ascending order
        usort($versions, array($this, 'compareMigrationVersions'));

        return $versions;
    }

    /**
     * @return Array version information.
     */
    protected function getMigrationVersion($file) {

        $name = basename($file, '.php');

        $number = 0;

        if (preg_match('/^(_*)(\d+)/', $name, $m)) {

            // Prefixing w/ 1 or more '_' characters means it gets a much
            // higher priority.

            $number = $m[2];
            if ($m[1]) {
                $number = ((strlen($m[1]) * OCTOPUS_MIGRATIONS_RUN_FIRST_OFFSET) + $number);
            }
        }

        $name = preg_replace('/^_*\d+_*/', '', $name);
        $name = strtolower($name);

        $hash = sha1($name . '|' . $number);

        return compact('hash', 'name', 'number', 'file');
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

        return strcasecmp($x['name'], $y['name']);

    }

    private function compareMigrationFiles($x, $y) {

        $xVersion = $this->getMigrationVersion($x);
        $yVersion = $this->getMigrationVersion($y);

        return $this->compareMigrationVersions($xVersion, $yVersion);
    }


    private function runMigrations($versions, $up, $undoOnException) {

        $run = array();

        $schema = new Octopus_DB_Schema($this->db);

        try {

            foreach($versions as $version) {

                if ($up) {
                    $this->applyVersion($version, $schema);
                } else {
                    $this->unapplyVersion($version, $schema);
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
    protected function applyVersion($version, $schema) {

        if ($this->versionHasBeenApplied($version)) {
            return;
        }

        $migration = $this->createMigration($version);

        if (method_exists($migration, 'up')) {
            $migration->up($this->db, $schema);
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
    protected function unapplyVersion($version, $schema) {

        if (!$this->versionHasBeenApplied($version)) {
            return;
        }

        $migration = $this->createMigration($version);

        if (method_exists($migration, 'down')) {
            $migration->down($this->db, $schema);
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

    /**
     * Given two migration versions, figures out min, max, and the direction
     * being moved in.
     */
    private function figureOutVersionStuff($fromVersion, $toVersion, &$minVersion, &$maxVersion, &$up) {

        if ($toVersion === 0 && $fromVersion !== 0) {
            $maxVersion = $this->getCurrentVersion();
            $minVersion = $this->getVersionByNumber(0);
            $up = false;
            return;
        }

        if ($fromVersion === null) {
            $fromVersion = $this->getCurrentVersion();
        } else if (is_numeric($fromVersion)) {
            $number = $fromVersion;
            $fromVersion = $this->getVersionByNumber($number);
            if ($fromVersion === null) {
                throw new Octopus_Exception("Invalid version number: $number");
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
            $number = $toVersion;
            $toVersion = $this->getVersionByNumber($number);
            if ($toVersion === null) {
                throw new Octopus_Exception("Invalid version number: $number");
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
            $up = true;
        } else {
            $minVersion = $toVersion;
            $maxVersion = $fromVersion;
            $up = false;
        }
    }

    private function getVersionByNumber($number) {

        $versions = $this->getAllVersions();

        if ($number === 0) {
            return array_shift($versions);
        }

        foreach($versions as $v) {
            if (intval($v['number']) === intval($number)) {
                return $v;
            }
        }

        return null;
    }

    private function getVersionByHash($hash) {

        $versions = $this->getAllVersions();
        foreach($versions as $v) {
            if ($v['hash'] === $hash) {
                return $v;
            }
        }

        return null;
    }

    private static function __requireOnce($file) {
        require_once($file);
    }

}

