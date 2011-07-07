<?php


Octopus::loadClass('Octopus_Model');
Octopus::loadClass('Octopus_Nav');
Octopus::loadClass('Octopus_Dispatcher');
Octopus::loadClass('Octopus_Request');
Octopus::loadClass('Octopus_Response');
Octopus::loadClass('Octopus_Controller');
Octopus::loadClass('Octopus_Controller_Api');
Octopus::loadClass('Octopus_Settings');

// Shortcut functions
function app_error($error, $level = E_USER_WARNING) {
    Octopus_App::singleton()->error($error, $level);
}

/**
 * Central class for an app instance.
 */
class Octopus_App {

    public static $defaults = array(

        /**
         * Whether to squash redirects when a PHP error occurs. This will only
         * happen in DEV mode.
         */
        'cancel_redirects_on_error' => true,

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

        /**
         * Whether or not to load all model classes by default.
         */
        'load_models' => true,

        'session_name' => 'octopus',

        /**
         * Whether or not to redirect to a 'welcome to octopus' view if no
         * config file is available.
         */
        'show_welcome' => false,

        /**
         * Extensions that view files can have.
         */
        'view_extensions' => array('.php', '.tpl')

    );

    private static $_instance = null;

    private $_options;
    private $_nav;
    private $_settings;
    private $_controllers = null, $_flatControllers = null;
    private $_prevErrorHandler = null;
    private $_currentRequest = null;
    private $_haveSiteConfig = false;

    private function __construct($options = array()) {

        $this->_options = empty($options) ? self::$defaults : array_merge(self::$defaults, $options);


        $this->_setUpPHP();
        $this->_figureOutDirectories();
        $this->_figureOutSecurity();
        $this->_figureOutLocation();
        $this->_initNav();
        $this->_loadSystemModels();
        $this->_loadSiteConfig();
        $this->_setEnvironmentFlags();
        $this->_ensurePrivateDir();
        $this->_initSettings();
        $this->watchForErrors();
    }

    protected function watchForErrors() {
        $this->_prevErrorHandler = set_error_handler(array($this, 'errorHandler'));
    }

    /**
     * Custom PHP error handler used by the application.
     */
    public function errorHandler($level, $err, $file, $line) {

        $isErrorOrWarning = ($level & E_ERROR) || ($level & E_WARNING) || ($level & E_USER_WARNING) || ($level & E_USER_ERROR);

        if ($isErrorOrWarning && $this->isDevEnvironment()) {

            if (!empty($this->_options['cancel_redirects_on_error'])) {
                cancel_redirects();
            }

        }

        if ($this->_prevErrorHandler) {
            $args = func_get_args();
            call_user_func_array($this->_prevErrorHandler, $args);
        }
    }

    public function __get($name) {

        if (isset($this->_options[$name])) {
            return $this->_options[$name];
        } else if (isset(self::$defaults[$name])) {
            return self::$defaults[$name];
        }

        throw new Octopus_Exception("Can't read key $name on Octopus_App");
    }

    public function __isset($name) {
        return isset($this->_options[$name]) || isset(self::$defaults[$name]);
    }

    private function _loadSystemModels() {

        $o =& $this->_options;
        if (!$o['load_models']) {
            return;
        }

        $this->_loadFilesFromDirectory($o['OCTOPUS_DIR'] . 'models');
    }

    private function _initSettings() {

        $o =& $this->_options;

        $this->_settings = new Octopus_Settings();

        foreach(array($o['OCTOPUS_DIR'], $o['SITE_DIR']) as $dir) {

            $settingsFile = $dir . 'settings.yaml';

            if (is_file($settingsFile)) {
                $this->_settings->addFromFile($settingsFile);
            }

        }

        // TODO: add settings from modules.


    }

    private function _ensurePrivateDir() {

        if (!is_dir($this->_options['OCTOPUS_PRIVATE_DIR'])) {
            if (!@mkdir($this->_options['OCTOPUS_PRIVATE_DIR'])) {
                $this->error('Unable to create private directory: ' . $this->_options['OCTOPUS_PRIVATE_DIR']);
            }
        }

    }

    private function _initNav() {

        $this->_nav = new Octopus_Nav();

    }

    public function alias($what, $toWhat) {
        $nav = $this->getNav();
        $nav->alias($what, $toWhat);
        return $this;
    }

    /**
     * @return Array An array of the effective app settings.
     */
    public function &getAllSettings() {

        $result = self::$defaults;
        $result = array_merge($result, $this->_options);

        $settings = $this->getSettings()->toArray();
        $result  = array_merge($result, $settings);

        return $result;
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

        Octopus::loadClass('Octopus_DB_Schema');

        $db = Octopus_DB::singleton();
        $schema = new Octopus_DB_Schema();

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
            $this->_nav = new Octopus_Nav();
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

            foreach(safe_glob($d . 'controllers/*.php') as $f) {

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
     * @return Object The Octopus_Request instance generated the last time
     * getResponse() was called on this app instance.
     */
    public function getCurrentRequest() {
        return $this->_currentRequest;
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

    /**
     * @param $request Octopus_Request The request to use when figuring out the
     * theme.
     * @return String The current theme to use.
     */
    public function getTheme($request = null) {

        if ($request === null) $request = $this->getCurrentRequest();

        if (is_object($request)) {
            $request = $request->getPath();
        }

        $key = 'site.theme';
        $parts = array_filter(explode('/', $request), 'trim');
        if (!empty($parts)) {
            $key .= '.' . implode('.', $parts);
        }


        return $this->getSetting($key);
    }

    public function getHostname() {
        return $this->_options['HTTP_HOST'];
    }

    /**
     * @deprecated Use getSetting instead.
     */
    public function getOption($name) {
        return $this->getSetting($name);
    }

    /**
     * @return Array The options for the app.
     */
    public function getOptions() {
        return $this->_options;
    }

    public function getNav() {

        if (!$this->_nav) {
            $this->_nav = new Octopus_Nav();
        }

        return $this->_nav;

    }

    /**
     * Returns an <b>Octopus_Response</b> for the given path.
     * @param $path String The path being requested.
     * @param $options Array Options dictating how the request should be
     * processed.
     * @return Object An Octopus_Response instance.
     */
    public function getResponse($path = null, $options = null) {

        $o =& $this->_options;

        if (is_array($path) && $options === null) {
            // Support getResponse(array('option' => 'value'));
            $options = $path;
            $path = null;
        }

        if (is_bool($options)) {
            // Support getResponse($path, $buffer)
            $options = array('buffer' => $options);
        }

        if ($path === null) {
            // Path not specified, so see if mod_rewrite can tell us.
            $arg = $this->_options['path_querystring_arg'];
            $path = isset($_GET[$arg]) ? $_GET[$arg] : '/';
            unset($_GET[$arg]);
        }

        if ($o['show_welcome'] && !$this->haveSiteConfig()) {
            // Redirect to a welcome view w/ setup instructions

            $path = 'sys/welcome';
        }

        $dispatch = new Octopus_Dispatcher($this);
        $this->_currentRequest = $req = $this->createRequest($path, $options);

        if ($o['show_welcome'] && !$this->haveSiteConfig()) {

            // Redirect to a welcome page
            if (!preg_match('/^sys($|\/.*)$/i', $req->getResolvedPath())) {
                $response = new Octopus_Response($options['buffer']);
                $response->redirect('/sys/welcome');
                return $response;
            }

        }

        return $dispatch->getResponse($this->_currentRequest, !empty($options['buffer']));
    }

    /**
     * @return bool Whether a site config file was loaded when this app was
     * started.
     */
    public function haveSiteConfig() {
        return $this->_haveSiteConfig;
    }

    /**
     * Generates an Octopus_Request for the given path/options.
     * @param $path String Path being requested.
     * @param $options Array Options for this request.
     * @return Object An <b>Octopus_Request</b> instance.
     */
    public function createRequest($path, $options = array()) {

        $originalPath = $path;

        // Octopus_Nav handles aliasing etc, so it can tell us what the
        // 'real' requested path is.

        $nav = $this->getNav();

        return new Octopus_Request($this, $originalPath, $nav->resolve($path), $options);
    }

    /**
     * Gets the value of a setting. First checks the $options array passed to
     * the app's constructor, then consults the more robust app settings class.
     * @param $name String The name of the setting to get the value for.
     * @return Mixed the value of a setting.
     */
    public function getSetting($name) {

        if (isset($this->_options[$name])) {
            return $this->_options[$name];
        } else if (isset(self::$defaults[$name])) {
            return self::$defaults[$name];
        } else {
            return $this->_settings->get($name);
        }

    }

    /**
     * @return Object The Octopus_Settings instance managing this
     * app's settings.
     */
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

        $instance = self::start();

        return $instance;
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

        $app = new Octopus_App($options);
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
        $dirs = array('OCTOPUS_DIR', 'ROOT_DIR',  'SITE_DIR', 'OCTOPUS_INCLUDES_DIR', 'OCTOPUS_FUNCTIONS_DIR', 'OCTOPUS_CLASSES_DIR', 'OCTOPUS_PRIVATE_DIR', 'OCTOPUS_EXTERNALS_DIR');

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

                        case 'OCTOPUS_PRIVATE_DIR':
                            $o[$dir] = $o['ROOT_DIR'] . '_private';
                            break;

                        case 'OCTOPUS_EXTERNALS_DIR':
                            $o[$dir] = $o['OCTOPUS_DIR'] . 'externals';
                            break;

                        case 'OCTOPUS_INCLUDES_DIR':
                            $o[$dir] = $o['OCTOPUS_DIR'] . 'includes/';
                            break;

                        case 'OCTOPUS_FUNCTIONS_DIR':
                            $o[$dir] = $o['OCTOPUS_INCLUDES_DIR'] . 'functions/';
                            break;

                        case 'OCTOPUS_CLASSES_DIR':
                            $o[$dir] = $o['OCTOPUS_INCLUDES_DIR'] . 'classes/';
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
        $this->_haveSiteConfig = false;

        if (!$o['use_site_config']) {
            return;
        }

        $configFile = $o['SITE_DIR'] . 'config.php';

        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : false;
        if (!$host && isset($o['HTTP_HOST'])) $host = $o['HTTP_HOST'];

        if (!$host) {
            // NOTE: Getting the hostname via `hostname` can have unintended
            // consequences when multiple app installations are running on the
            // same server.
            die("No hostname is known.");
        }
        $host = strtolower($host);

        $hostConfigFile = SITE_DIR . "config.$host.php";

        if (file_exists($configFile)) {
            $this->_haveSiteConfig = true;
            require_once($configFile);
        }

        if (file_exists($hostConfigFile)) {
            $this->_haveSiteConfig = true;
            require_once($hostConfigFile);
        }

        // Nav Structure
        $navFile = $o['SITE_DIR'] . 'nav.php';
        if (!file_exists($navFile)) {
            //$this->error('No nav.php found in site dir.', E_USER_NOTICE);
        } else {
            require_once($navFile);
        }

        // Models
        if ($o['load_models']) {
            $this->_loadFilesFromDirectory($o['SITE_DIR'] . 'models');
        }
    }

    private function _loadFilesFromDirectory($dir) {

        foreach(safe_glob(rtrim($dir, '/') . '/*.php') as $file) {
            require_once($file);
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

        if (!isset($o['DEV'])) {

            $o['DEV'] = is_dev_environment($o['LIVE'], $o['STAGING'], false, $this->getHostname());

            if (!$o['DEV'] && !(isset($o['STAGING']) || isset($o['LIVE']))) {
                // If we have no other evidence, default to a dev environment
                // if the site config is not present
                $o['DEV'] = !$this->haveSiteConfig();
            }

        }

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
            session_start($this->getOption('session_name'));
        }

        $tz = @date_default_timezone_get();
        if (!$tz) {
            $tz = 'America/Los_Angeles';
        }
        date_default_timezone_set($tz);

    }


}

?>
