<?php

SG::loadClass('SG_Model');
SG::loadClass('SG_Nav');
SG::loadClass('SG_Dispatcher');
SG::loadClass('SG_Response');
SG::loadClass('SG_Controller');
SG::loadClass('SG_Settings');

// Shortcut functions
function app_error($error, $level = E_USER_WARNING) {
    SG_App::singleton()->error($error, $level);
}

/**
 * Central class for an app instance.
 */
class SG_App {

    public static $defaults = array(

        /**
         * Whether the app is running over HTTPS. NULL means the app will
         * figure it out for itself.
         */
        'https' => null,

        /**
         * Querystring argument used my mod_rewrite for nice URLs.
         */
        'path_querystring_arg' => '__path',

        /**
         * Whether or not to create defines.
         */
        'use_defines' => true,

        /**
         * Whether or not to set a bunch of global variables, e.g.
         * $URL_BASE.
         */
        'use_globals' => true,

        /**
         * Whether or not to load site-specific configuration.
         */
        'use_site_config' => true,

    );

    private static $_instance = null;

    private $_options;
    private $_nav;
    private $_settings;
    private $_controllers = null, $_flatControllers = null;

    private function __construct($options = array()) {

        $this->_options = empty($options) ? self::$defaults : array_merge(self::$defaults, $options);

        $this->_setUpPHP();
        $this->_figureOutDirectories();
        $this->_figureOutSecurity();
        $this->_figureOutLocation();
        $this->_initNav();
        $this->_loadSiteConfig();
        $this->_setEnvironmentFlags();
        $this->_ensurePrivateDir();
        $this->_initSettings();

    }

    private function _initSettings() {

        $o =& $this->_options;

        $this->_settings = new SG_Settings();

        foreach(array($o['OCTOPUS_DIR'], $o['SITE_DIR']) as $dir) {

            $settingsFile = $dir . 'settings.yaml';

            if (is_file($settingsFile)) {
                $this->_settings->addFromFile($settingsFile);
            }

        }

        // TODO: add settings from modules.


    }

    private function _ensurePrivateDir() {

        if (!is_dir($this->_options['PRIVATE_DIR'])) {
            if (!@mkdir($this->_options['PRIVATE_DIR'])) {
                $this->error('Unable to create private directory: ' . $this->_options['PRIVATE_DIR']);
            }
        }

    }

    private function _initNav() {

        $this->_nav = new SG_Nav();

    }

    /**
     * Installs an app instance, runs DB migrations, etc.
     */
    public function install() {

        $modules = array(
            'core' => $this->getOption('OCTOPUS_DIR')
        );

        $result = array(
            'modules' => array(
            )
        );

        SG::loadClass('SG_DB_Schema');

        $db = SG_DB::singleton();
        $schema = new SG_DB_Schema();

        foreach($modules as $name => $root) {

            // TODO compare versions etc

            $root = rtrim($root, '/');

            $migrationsFile = $root . '/migrations.php';

            if (is_file($migrationsFile)) {

                require_once($migrationsFile);

                $func = 'migrate_' . $name;
                if (function_exists($func)) {
                    $func($db, $schema);
                    $result['modules'][] = $name;
                }

            }

        }

        return $result;
    }

    /**
     * Logs an error.
     */
    public function error($message, $level = E_USER_WARNING) {
        trigger_error($message, $level);
    }


    public function find($path, $options = null) {

        if (!$this->_nav) {
            $this->_nav = new SG_Nav();
        }

        return $this->_nav->find($path, $options);
    }

    /**
     * @return Array A hierarchical list of controllers.
     */
    public function getControllers($flat = false) {

        if ($this->_controllers && !$flat) {
            return $this->_controllers;
        } else if ($this->_flatControllers && $flat) {
            return $this->_flatControllers;
        }

        $o =& $this->_options;
        $found = array();

        $dirs = array($o['OCTOPUS_DIR'], $o['SITE_DIR']);

        foreach($dirs as $d) {

            foreach(glob($d . 'controllers/*.php') as $f) {

                $parts = explode('_', basename($f, '.php'));

                $this->fillOutControllerHierarchy($found, $parts);

            }

        }

        if ($flat) {
            $found = $this->flattenControllerHierarchy($found);
            $this->_flatControllers = $found;
            return $found;
        } else {
            $this->_controllers = $found;
            return $found;
        }
    }

    private function fillOutControllerHierarchy(&$h, &$parts) {

        if (empty($parts)) {
            return;
        }

        while(($p = array_shift($parts)) !== null) {

            if (!$p) {
                continue;
            }

            if (!isset($h[$p])) {
                $h[$p] = array();
            }

            $this->fillOutControllerHierarchy($h[$p], $parts);
            return;
        }

    }

    /**
     * Calls get_file using the options specified for this app instance.
     * @return Mixed The path to the file, if found, or false if it is not
     * found.
     */
    public function getFile($paths, $dirs = null, $options = null) {

        $o =& $this->_options;

        if ($dirs == null) {
            $dirs = array($o['SITE_DIR'], $o['OCTOPUS_DIR']);
        }

        if ($options === null) $options = array();
        if (!$this->isDevEnvironment()) $options['debug'] = false;

        return get_file($paths, $dirs, $options);
    }

    public function getHostname() {
        return $this->_options['HTTP_HOST'];
    }

    public function getOption($name, $default = null) {
        return isset($this->_options[$name]) ? $this->_options[$name] : $default;
    }

    /**
     * @return Array The options for the app.
     */
    public function getOptions() {
        return $this->_options;
    }

    public function getNav() {

        if (!$this->_nav) {
            $this->_nav = new SG_Nav();
        }

        return $this->_nav;

    }

    /**
     * Returns a response for the given path.
     * @return Object An SG_Response instance.
     */
    public function getResponse($path = null) {

        if ($path === null) {
            $arg = $this->_options['path_querystring_arg'];
            $path = isset($_GET[$arg]) ? $_GET[$arg] : '/';
            unset($_GET[$arg]);
        }

        $dispatch = new SG_Dispatcher($this);
        $response = $dispatch->getResponse($path);

        return $response;
    }

    public function getSettings() {
        return $this->_settings;
    }

    public function isDevEnvironment() {
        return $this->_options['DEV'];
    }

    /**
     * @return bool Whether the app is running over HTTPS.
     */
    public function isHTTPS() {
        return $this->_options['https'];
    }

    public function isLiveEnvironment() {
        return $this->_options['LIVE'];
    }

    public function isStagingEnvironment() {
        return $this->_options['STAGING'];
    }

    public function makeUrl($path) {
        return make_url(
            $path,
            null,
            array('URL_BASE' => $this->_options['URL_BASE'])
        );
    }

    public static function &singleton() {

        if (self::$_instance) {
            return self::$_instance;
        }

        return self::start();
    }

    /**
     * @return bool Whether there is already a running instance of the app.
     */
    public static function isStarted() {
        return !!self::$_instance;
    }

    /**
     * Spins up a new application instance.
     */
    public static function start($options = array()) {

        $app = new SG_App($options);
        if (!self::$_instance) {
            self::$_instance = $app;
        }

        return $app;

    }


    /**
     * Builds out the list of directories used by Octopus and optionally
     * registers their locations as defines and global variables.
     */
    private function _figureOutDirectories() {

        $o = &$this->_options;
        $dirs = array('OCTOPUS_DIR', 'ROOT_DIR',  'SITE_DIR', 'INCLUDES_DIR', 'FUNCTIONS_DIR', 'CLASSES_DIR', 'PRIVATE_DIR', 'EXTERNALS_DIR');

        foreach($dirs as $dir) {

            if (empty($o[$dir])) {

                if (defined($dir)) {
                    $o[$dir] = constant($dir);
                } else {

                    switch($dir) {

                        case 'OCTOPUS_DIR':
                            //         classes  includes  octopus
                            $o[$dir] = dirname( dirname(  dirname(__FILE__)));
                            break;

                        case 'ROOT_DIR':
                            $o[$dir] = dirname($o['OCTOPUS_DIR']);
                            break;

                        case 'SITE_DIR':
                            $o[$dir] = $o['ROOT_DIR'] . 'site';
                            break;

                        case 'PRIVATE_DIR':
                            $o[$dir] = $o['ROOT_DIR'] . '_private';
                            break;

                        case 'EXTERNALS_DIR':
                            $o[$dir] = $o['OCTOPUS_DIR'] . 'externals';
                            break;

                        case 'INCLUDES_DIR':
                            $o[$dir] = $o['OCTOPUS_DIR'] . 'includes/';
                            break;

                        case 'FUNCTIONS_DIR':
                            $o[$dir] = $o['INCLUDES_DIR'] . 'functions/';
                            break;

                        case 'CLASSES_DIR':
                            $o[$dir] = $o['INCLUDES_DIR'] . 'classes/';
                            break;

                    }

                }

            }

            $o[$dir] = rtrim($o[$dir], '/') . '/';

            if ($o['use_defines']) {
                define_unless($dir, $o[$dir]);
            }

            if ($o['use_globals']) {
                $GLOBALS[$dir] = $o[$dir];
            }
        }

    }

    private function _figureOutSecurity() {

        $o =& $this->_options;

        if ($o['https'] === null) {
            $o['https'] = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on';
        }

    }

    /**
     * Discovers the base for all URLs to be generated by the app, e.g.
     * '/' or '/subdir/'.
     */
    private function _figureOutLocation() {

        $o =& $this->_options;

        if (empty($o['HTTP_HOST'])) {

            if (isset($_SERVER['HTTP_HOST'])) {
                $o['HTTP_HOST'] = $_SERVER['HTTP_HOST'];
            } else {
                $o['HTTP_HOST'] = trim(`hostname`);
            }

        }


        if (empty($o['URL_BASE'])) {

            if (defined('URL_BASE')) {
                $o['URL_BASE'] = URL_BASE;
            } else {

                $o['URL_BASE'] = find_url_base();
                if ($o['URL_BASE'] === false) {
                    $this->error('Could not determine URL_BASE. Assuming "/".');
                    $o['URL_BASE'] = '/';
                }
            }

        }

        if ($o['use_defines']) {
            define_unless('URL_BASE', $o['URL_BASE']);
        }

        if ($o['use_globals']) {
            $GLOBALS['URL_BASE'] = $o['URL_BASE'];
        }
    }

    private function flattenControllerHierarchy($h, $inProgress = '', &$result = array()) {

        foreach($h as $key => $children) {

            $item = $inProgress . ($inProgress ? '_' : '') . $key;

            if (count($children)) {
                $this->flattenControllerHierarchy($children, $item, $result);
            } else {
                $result[] = $item;
            }
        }

        return $result;

    }

    /**
     * Loads any config files from the sitedir.
     */
    private function _loadSiteConfig() {

        $o =& $this->_options;

        if (!$o['use_site_config']) {
            return;
        }

        $configFile = $o['SITE_DIR'] . 'config.php';

        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : trim(`hostname`);
        $hostConfigFile = SITE_DIR . "config.$host.php";

        if (! (file_exists($configFile) || file_exists($hostConfigFile))) {
            //$this->error('No config file found.', E_USER_NOTICE);
            return false;
        }

        if (file_exists($configFile)) {
            require_once($configFile);
        }

        if (file_exists($hostConfigFile)) {
            require_once($hostConfigFile);
        }

        // Nav Structure
        $navFile = $o['SITE_DIR'] . 'nav.php';
        if (!file_exists($navFile)) {
            //$this->error('No nav.php found in site dir.', E_USER_NOTICE);
        } else {
            require_once($navFile);
        }
    }


    private function _setEnvironmentFlags() {

        $o =& $this->_options;

        $flags = array('DEV', 'LIVE', 'STAGING');
        foreach($flags as $f) {

            if (isset($o[$f])) {
                continue;
            }

            if ($o['use_defines'] && defined($f)) {
                $o[$f] = constant($f);
            } else {
                $o[$f] = null;
            }
        }

        if (!isset($o['DEV'])) $o['DEV'] = is_dev_environment($o['LIVE'], $o['STAGING'], false, $this->getHostname());
        if (!isset($o['STAGING'])) $o['STAGING'] = is_staging_environment($o['DEV'], $o['LIVE'], false, $this->getHostname());
        if (!isset($o['LIVE'])) $o['LIVE'] = is_live_environment($o['DEV'], $o['STAGING'], false);

        if ($o['use_defines']) {
            define_unless('DEV', $o['DEV']);
            define_unless('STAGING', $o['STAGING']);
            define_unless('LIVE', $o['LIVE']);
        }

        if ($o['use_globals']) {
            $GLOBALS['DEV'] = $o['DEV'];
            $GLOBALS['STAGING'] = $o['STAGING'];
            $GLOBALS['LIVE'] = $o['LIVE'];
        }

    }


    /**
     * Brings the PHP environment up to a known good state.
     */
    private function _setUpPHP() {

        if (!session_id()) {
            session_start('octopus');
        }

        // TODO: figure out a better way to do this?
        $tz = @date_default_timezone_get();
        $err = error_get_last();
        if ($err) {
            if (strstr($err['message'], 'date_default_timezone_get')) {
                date_default_timezone_set('America/Los_Angeles');
            }
        }

    }


}

?>
