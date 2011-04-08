<?php

SG::loadClass('SG_Model');

// Shortcut functions
function app_error($error, $level = E_WARN) {
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

    private function __construct($options = array()) {

        $this->_options = empty($options) ? self::$defaults : array_merge(self::$defaults, $options);

        $this->_setUpPHP();
        $this->_figureOutDirectories();
        $this->_figureOutSecurity();
        $this->_findUrlBase();
        $this->_loadSiteConfig();
        $this->_setEnvironmentFlags();

    }

    /**
     * Logs an error.
     */
    public function error($message, $level = E_WARN) {
        trigger_error($message, $level);
    }


    public function find($path, $options = null) {
        return $this->_nav->find($path, $options);
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
        $response->flush();
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


    public static function &singleton() {

        if (self::$_instance) {
            return self::$_instance;
        }

        return self::start();
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
        $dirs = array('OCTOPUS_DIR', 'ROOT_DIR',  'SITE_DIR', 'INCLUDES_DIR', 'FUNCTIONS_DIR', 'CLASSES_DIR');

        foreach($dirs as $dir) {

            if (empty($o[$dir])) {

                if (defined($dir)) {
                    $o[$dir] = $dir;
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
    private function _findUrlBase() {

        $o =& $this->_options;

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
            $this->error('No config file found.');
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
            $this->error('No nav.php found in site dir.');
        } else {
            require_once($navFile);
        }
    }


    private function _setEnvironmentFlags() {

        $o =& $this->_options;

        if (!isset($o['DEV'])) $o['DEV'] = is_dev_environment($o);
        if (!isset($o['STAGING'])) $o['STAGING'] = is_staging_environment($o);
        if (!isset($o['LIVE'])) $o['LIVE'] = is_live_environment($o);

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

        session_start();

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
