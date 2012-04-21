<?php

/**
 * Central class for an app instance.
 */
class Octopus_App {

    /**
     * Default values for the $options array passed to Octopus_App::start().
     */
    public static $defaults = array(

        /**
         * The default template inside which to render the current view's
         * contents. This will be evaluated as relative to the
         * /site/templates or /octopus/templates directories. Any valid
         * view extensions (e.g., .tpl, .php) will be appended, so
         * for the value 'html/page', both 'html/page.tpl' and 'html/page.php'
         * will be tried.
         */
        'default_template' => 'html/page',

        /**
         * Whether or not to create directories used by octopus. If true
         * and the dirs can't be created, an exception will be thrown.
         */
        'create_dirs' => true,

        /**
         * Alias to define for the '/' path. Set to false to not define one.
         */
        'root_alias' => 'sys/welcome',

        /**
         * Whether to squash redirects when a PHP error occurs. This will only
         * happen in DEV mode.
         */
        'cancel_redirects_on_error' => true,

        /**
         * Querystring argument used my mod_rewrite for nice URLs.
         */
        'path_querystring_arg' => '__path',

        /**
         * Whether or not to make the new app instance the one returned by
         * Octopus_App::singleton
         */
        'use_singleton' => true,

        /**
         * Whether or not to set defines for global octopus variables (DEV,
         * LIVE, STAGING, URL_BASE, etc).
         */
        'use_defines' => true,

        /**
         * Whether or not to set globals for important octopus variables ($DEV,
         * $LIVE, $STAGING, $URL_BASE, etc).
         */
        'use_globals' => true,

        /**
         * Whether or not to load site-specific configuration.
         */
        'use_site_config' => true,

        /**
         * Whether or not to auto-include files in the site/functions directory.
         * This option depends on use_site_config, if that is false, none
         * will be included.
         */
        'include_site_functions' => true,

        /**
         * Whether or not to load all model classes by default.
         */
        'load_models' => true,

        /**
         * Enable PHP session support
         */
        'use_sessions' => true,

        /**
         * Whether to use the theme system.
         */
        'use_themes' => true,

        /**
         * PHP session name.
         */
        'session_name' => 'octopus',

        /**
         * Extensions that view files can have.
         */
        'view_extensions' => array('.php', '.tpl')

    );

    private static $_instance = null;

    private $_options;
    private $_router;
    private $_settings;
    private $_prevErrorHandler = null;
    private $_currentRequest = null;
    private $_currentResponse = null;

    private $_haveSiteDir = false;
    private $_haveSiteControllers = false;
    private $_haveSiteConfig = false;

    private $_renderer = null;

    private function __construct($options = array()) {

        $this->_options = empty($options) ? self::$defaults : array_merge(self::$defaults, $options);

        if (!self::$_instance && !empty($this->_options['use_singleton'])) {
            self::$_instance = $this;
        }

        $this->_setUpPHP();
        $this->_figureOutDirectories();
        $this->_figureOutSecurity();
        $this->_figureOutLocation();
        $this->_loadSystemModels();
        $this->_examineSiteDir();
        $this->_loadSiteConfig();
        $this->_setEnvironmentFlags();
        $this->_configureLoggingAndDebugging();
        $this->ensureDirectoriesExist();
        $this->_initSettings();
        $this->watchForErrors();
        $this->_includeSiteFunctions();

    }

    private function _examineSiteDir() {

        $o =& $this->_options;

        $this->_haveSiteConfig = false;
        $this->_haveSiteViews = false;
        $this->_haveSiteControllers = false;

        $siteDir = $o['SITE_DIR'];
        if (!is_dir($siteDir)) {
            return;
        }

        $viewsDir = $o['SITE_DIR'] . 'views/';

        if (is_dir($viewsDir)) {

            foreach($o['view_extensions'] as $ext) {

                $files = glob($viewsDir . '*' . $ext);
                if (!empty($files)) {
                    $this->_haveSiteViews = true;
                    break;
                }

            }

        }

        $controllersDir = $o['SITE_DIR'] . 'controllers/';
        if (is_dir($controllersDir)) {
            $files = glob($controllersDir . '*.php');
            $this->_haveSiteViews = !empty($files);
        }

    }

    protected function watchForErrors() {
        $this->_prevErrorHandler = set_error_handler(array($this, 'errorHandler'));
        register_shutdown_function(array($this, 'shutdownHandler'));
    }

    /**
     * Custom shutdown handler used to flush the response in the case of
     * a fatal error. This ensures that
     */
    public function shutdownHandler() {

        $error = error_get_last();
        if ($error && !empty($error['type']) && ($error['type'] & E_ERROR)) {
            $resp = $this->getCurrentResponse();
            if ($resp) $resp->flush();
        }

    }

    /**
     * Custom PHP error handler used by the application.
     */
    public function errorHandler($level, $err, $file, $line, $context) {

		if (!(error_reporting() & $level)) {
			// This error should not be shown.
			return true;
		}

    	$isNonSevere = ($level === E_NOTICE) ||
    				   ($level === E_DEPRECATED) ||
    				   ($level === E_STRICT) ||
    				   ($level === E_USER_NOTICE);

        $isSevere = !$isNonSevere;

        if ($isSevere && $this->isDevEnvironment()) {

            if (!empty($this->_options['cancel_redirects_on_error'])) {
                cancel_redirects();
            }

        }

        $resp = $this->getCurrentResponse();

        if ($resp && $isSevere) {
    		$resp->setStatus(500);
        }

		// Pass errors on to Octopus_Log to distribute them to listeners
        Octopus_Log::errorHandler($level, $err, $file, $line, $context);

        if ($resp) {
            // Ensure client receives whatever we've been working on.
			$resp->flush();
        }

        if ($this->_prevErrorHandler && !function_exists('xdebug_enable')) {
            $args = func_get_args();
            call_user_func_array($this->_prevErrorHandler, $args);
        }

        return true;
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

    private function ensureDirectoriesExist() {

        $o =& $this->_options;

        if (!$o['create_dirs']) {
            return;
        }

        foreach(array('OCTOPUS_PRIVATE_DIR', 'OCTOPUS_CACHE_DIR', 'OCTOPUS_UPLOAD_DIR') as $name) {

            $dir = $o[$name];
            if (!is_dir($dir)) {
                if (!@mkdir($dir, 0777, true)) {
                    throw new Octopus_Exception("Unable to create $name: '$dir'");
                }
            }

        }

    }

    /**
     * Adds an alias to this app's router.
     * Note that the order of arguments here is $to, $from rather than
     * router's $from, $to. Sorry about that.
     */
    public function alias($to, $from, $options = array()) {
        $r = $this->getRouter();
        $r->alias($from, $to, $options);
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
     * Logs an error.
     */
    public function error($message, $level = E_USER_WARNING) {
        trigger_error($message, $level);
    }

    /**
     * @return An Octopus_Renderer instance to use to find views and render
     * the final page.
     */
    public function getRenderer() {

    	if ($this->_renderer) {
    		return $this->_renderer;
    	}

        return ($this->_renderer = Octopus::create('Octopus_Renderer', array($this)));
    }

    /**
     * Runs migrations up to / back to $version.
     * @return Number The current DB version.
     */
    public function migrate($version = null) {

        $runner = new Octopus_DB_Migration_Runner($this->getMigrationDirs());
        $version = $runner->migrate($version);

        // TODO: Auto-migrate all models

        return $version;
    }

    /**
     * @return Bool Whether there are any migrations that need to be run.
     */
    public function haveMigrationsToRun() {

        $runner = new Octopus_DB_Migration_Runner($this->getMigrationDirs());

        return $runner->isUpToDate();
    }

    private function getMigrationDirs() {
        return array(
            $this->getOption('OCTOPUS_DIR') . 'migrations/',
            $this->getOption('SITE_DIR') . 'migrations/',
        );
    }


    /**
     * @return Object The Octopus_Request instance generated the last time
     * getResponse() was called on this app instance.
     */
    public function getCurrentRequest() {
        return $this->_currentRequest;
    }

    /**
     * @return The Octopus_Response currently being assembled, or null
     * if none is in progress.
     */
    public function getCurrentResponse() {
        return $this->_currentResponse;
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

        if (!$this->_options['use_themes']) {
            return '';
        }

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

    /**
     * Sets the theme for the current request.
     */
    public function setTheme($theme, $request = null) {

        if (!$request) $request = $this->getCurrentRequest();

        $controller = $request->getController();
        $controller->theme = $theme;

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

    /**
     * @deprecated Use getRouter().
     */
    public function getNav() {
        return $this->getRouter();
    }

    /**
     * @return Octopus_Router The router responsible for mapping nice urls to
     * yucky internal ones.
     */
    public function getRouter() {

        if (!$this->_router) {

            $this->_router = new Octopus_Router();

            if (!empty($this->_options['root_alias'])) {
                $this->_router->alias('/', $this->_options['root_alias']);
            }

        }

        return $this->_router;
    }

    /**
     * Returns an <b>Octopus_Response</b> for the given path.
     * @param $path String The path being requested.
     * @param $options Array Options dictating how the request should be
     * processed.
     * @return Object An Octopus_Response instance. By default, this will be
     * a buffered response. For unbuffered responses, set the 'buffer' option
     * to false.
     */
    public function getResponse($path = null, $options = null) {

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

        if (!is_array($options)) {
            $options = array('buffer' => true);
        }

        if (!array_key_exists("buffer", $options)) {
            // Default to buffered response
            $options['buffer'] = true;
        }

        // Ensure there's no querystring on path. This doesn't come up in
        // normal app operation, but can sometime arise in testing.
        $qPos = strpos($path, '?');
        if ($qPos !== false) {
            $qs = substr($path, $qPos + 1);
            $path = substr($path, 0, $qPos);
            parse_str($qs, $_GET);
        }

        $this->_currentRequest = $req = $this->createRequest($path, $options);
        $this->_currentResponse = $resp = $this->createResponse($options['buffer']);

        $dispatch = new Octopus_Dispatcher($this);
        $dispatch->handleRequest($req, $resp);

        return $resp;
    }

    public function getGetResponse($path, $data = array(), $options = array()) {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = $data;
        return $this->getResponse($path, $options);
    }

    public function getDeleteResponse($path, $data = array(), $options = array()) {
        if (is_bool($options)) {
            $buffer = $options;
            $options = array(
                'buffer' => $buffer,
            );
        }

        return $this->fakeInputdata('delete', $path, $data, $options);
    }

    public function getPutResponse($path, $data = array(), $options = array()) {
        if (is_bool($options)) {
            $buffer = $options;
            $options = array(
                'buffer' => $buffer,
            );
        }

        return $this->fakeInputdata('put', $path, $data, $options);
    }

    private function fakeInputData($method, $path, $data, $options) {
        $method = strtolower($method);

        $file = tempnam('/tmp/', 'octopus_' . $method);
        file_put_contents($file, http_build_query($data));
        $options[$method . '_data_file'] = $file;
        $_SERVER['REQUEST_METHOD'] = $method;

        $response = $this->getResponse($path, $options);
        unlink($file);
        return $response;
    }

    /**
      * @deprecated
      */
    public function post($url, $data = array(), $options = array()) {
        return $this->getPostResponse($url, $data, $options);
    }

    public function getPostResponse($url, $data = array(), $options = array()) {

        // TODO don't do this

        foreach($_POST as $key => $value) {
            unset($_POST[$key]);
        }

        foreach($data as $key => $value) {
            $_POST[$key] = $value;
        }

        $_SERVER['REQUEST_METHOD'] = 'post';

        return $this->getResponse($url, $options);
    }

    /**
     * Generates an Octopus_Request for the given path/options.
     * @param $path String Path being requested.
     * @param $options Array Options for this request.
     * @return Object An <b>Octopus_Request</b> instance.
     */
    public function createRequest($path, $options = array()) {

        $originalPath = $path;

        // Octopus_Router handles aliasing etc, so it can tell us what the
        // 'real' requested path is.

        $router = $this->getRouter();

        return new Octopus_Request($this, $originalPath, $router->resolve($path), $options);
    }

    /**
     * @return Octopus_Response
     */
    public function createResponse($buffer = false) {
        return new Octopus_Response($buffer);
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
        return !empty($this->_options['DEV']);
    }

    /**
     * @return bool Whether the app is running over HTTPS.
     */
    public function isHTTPS() {
        return $this->_options['https'];
    }

    public function isLiveEnvironment() {
        return !empty($this->_options['LIVE']);
    }

    public function isStagingEnvironment() {
        return !empty($this->_options['STAGING']);
    }

    public function makeUrl($path, $querystring = null, $options = array()) {

        $options['URL_BASE'] = $this->_options['URL_BASE'];

        return make_url(
            $path,
            $querystring,
            $options
        );
    }

    public static function singleton() {

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
        $app = new Octopus_App($options);
        return $app;
    }

    /**
     * Shuts down / cleans up after this app instance.
     */
    public function stop() {

        if (self::$_instance === $this) {
            self::$_instance = null;
        }

           $o =& $this->_options;

        Octopus::removeControllerDir($o['OCTOPUS_DIR'] . 'controllers/');
        Octopus::removeClassDir($o['SITE_DIR'] . 'classes/');
        Octopus::removeControllerDir($o['SITE_DIR'] . 'controllers/');

    }

    private function _configureLoggingAndDebugging() {
    	Octopus_Debug::configure($this->_options, false);
    }


    /**
     * Builds out the list of directories used by Octopus and optionally
     * registers their locations as defines and global variables.
     */
    private function _figureOutDirectories() {

        $o = &$this->_options;
        $dirs = array('OCTOPUS_DIR', 'ROOT_DIR',  'SITE_DIR', 'OCTOPUS_INCLUDES_DIR', 'OCTOPUS_FUNCTIONS_DIR', 'OCTOPUS_CLASSES_DIR', 'OCTOPUS_PRIVATE_DIR', 'OCTOPUS_EXTERNALS_DIR', 'OCTOPUS_CACHE_DIR', 'OCTOPUS_UPLOAD_DIR');

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
                            $o[$dir] = $o['ROOT_DIR'] . 'site/';
                            break;

                        case 'OCTOPUS_PRIVATE_DIR':
                            $o[$dir] = $o['ROOT_DIR'] . 'private/';
                            break;

                        case 'OCTOPUS_EXTERNALS_DIR':
                            $o[$dir] = $o['OCTOPUS_DIR'] . 'externals/';
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

                        case 'OCTOPUS_CACHE_DIR':
                            $o[$dir] = $o['ROOT_DIR'] . 'cache/';
                            break;

                        case 'OCTOPUS_UPLOAD_DIR':
                            $o[$dir] = $o['SITE_DIR'] . 'files/';
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

        Octopus::addControllerDir($o['SITE_DIR'] . 'controllers/');
        Octopus::addControllerDir($o['OCTOPUS_DIR'] . 'controllers/');
        Octopus::addClassDir($o['SITE_DIR'] . 'classes/', true);
        // NOTE: OCTOPUS_DIR/includes/classes is added automatically by including includes/classes/Octopus.php
    }

    private function _figureOutSecurity() {

        $o =& $this->_options;

        if (!isset($o['https'])) {
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

            if ($o['use_defines'] && defined('URL_BASE')) {
                $o['URL_BASE'] = URL_BASE;
            } else {

                $o['URL_BASE'] = find_url_base($o['ROOT_DIR']);

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

    private function _includeSiteFunctions() {

        $o =& $this->_options;
        if (!($o['use_site_config'] && $o['include_site_functions'])) {
            return;
        }

        $funcDir = $o['SITE_DIR'] . 'functions/';
        if (is_dir($funcDir)) {
            $files = glob($funcDir . '*.php');
            if ($files) {
                foreach($files as $f) {
                    self::_require_once($f);
                }
            }
        }

    }

    private static function _require_once($file) {
        require_once($file);
    }

    /**
     * Loads any config files from the sitedir.
     */
    private function _loadSiteConfig() {

        $o =& $this->_options;
        $this->_haveSiteConfig = false;

        if (!$o['use_site_config']) {
            // This is used when running the unit tests
            return;
        }

        $configFile = $o['SITE_DIR'] . 'config.php';

        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : false;
        if (!$host && isset($o['HTTP_HOST'])) $host = $o['HTTP_HOST'];

        if (!$host) {

            /*
             * NOTE: Getting the hostname via `hostname` can have unintended
             * consequences when multiple app installations are running on the
             * same server. For example, if you have apps on serverx in two
             * places:
             *
             *  /var/www/app
             *  /var/www/app/dev
             *
             * If 'dev.serverx' is mapped to app/dev, but hostname returns
             * 'serverx', any config meant for dev.serverx will not be applied.
             */

            die("No hostname is known.");
        }

        $host = strtolower($host);

        $hostConfigFile = $o['SITE_DIR'] . "config.$host.php";

        if (file_exists($configFile)) {
            $this->_haveSiteConfig = true;
            require_once($configFile);
        }

        if (file_exists($hostConfigFile)) {
            $this->_haveSiteConfig = true;
            require_once($hostConfigFile);
        }

        // Load app routes
        // NOTE: nav.php is loaded for backwards compatibility
        foreach(array('nav.php', 'routes.php') as $f) {
            $file = $o['SITE_DIR'] . $f;
            if (is_file($file)) {
                self::loadRoutesFile($file, $this);
            }
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
                // if there's nothing in the site dir
                $o['DEV'] = !($this->_haveSiteConfig || $this->_haveSiteViews || $this->_haveSiteControllers);
            }

        }

        if (!isset($o['STAGING'])) $o['STAGING'] = is_staging_environment($o['DEV'], $o['LIVE'], false, $this->getHostname(), $o['URL_BASE']);
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

        if ($this->getOption('use_sessions')) {
            if (!session_id()) {
                session_start($this->getOption('session_name'));
            }
        } else {
            ini_set('session.use_cookies', '0');
        }

        $tz = @date_default_timezone_get();
        if (!$tz) {
            $tz = 'America/Los_Angeles';
        }
        date_default_timezone_set($tz);

    }

    private static function loadRoutesFile($file, Octopus_App $app) {

        // Make $APP, $NAV, and $ROUTES variables avilable to routes.php
        // file
        $APP = $app;
        $NAV = $ROUTES = $app->getRouter();

        // Use those vars to hopefully keep the build from bitching about
        // unused variables
        if ($APP || $NAV || $ROUTES) {
            require_once($file);
        }

    }

}
