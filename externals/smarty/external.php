<?php

define('SMARTY_VERSION', '3.0.8');
require_once(dirname(__FILE__) . '/Smarty-' . SMARTY_VERSION . '/libs/Smarty.class.php');

/**
 * Wrapper around a Smarty instance. Use Octopus_Smarty::trusted() to get an
 * instance for rendering trusted content and Octopus_Smarty::untrusted() to
 * get an instance for rendering untrusted content.
 */
class Octopus_Smarty {

	// TODO: make this private
    public $smarty;

    private $app;
    private $debug;
    private $theme;
    private $secureFunc;

    private static $trusted = null;
    private static $untrusted = null;
    private static $default = null;

    private function __construct($app, $debug, $theme, $secureFunc = null) {

    	$this->app = $app;
    	$this->debug = $debug ? $debug : false;
    	$this->theme = $theme ? $theme : '';
    	$this->secureFunc = $secureFunc ? $secureFunc : false;

    	$this->smarty = $this->createSmartyInstance();

    }

    /**
     * Creates a Smarty template based on a file and returns it.
     * @return Smarty_Internal_Template
     */
    public function createTemplate($file, $data = array()) {

	    // For relative paths, go from /views
        if ($file[0] !== '/') {

        	foreach(array('SITE_DIR', 'OCTOPUS_DIR') as $opt) {

        		$opt = $this->app ? $this->app->$opt : get_option($opt);
        		$found = $opt . 'views/' . $templateFile;

        		if (is_file($found)) {
        			$file = $found;
        			break;
        		}
        	}

        }

        $smartyData = $this->smarty->createData();

        if (is_array($data)) {
            foreach($data as $key => $value) {
                $smartyData->assign($key, $value);
            }
        }

        return $this->smarty->createTemplate($file, $smartyData);
    }

    /**
     * @return String Path to the directory used for smarty compilation.
     */
    public function getCompileDir() {

    	if ($this->app) {

    		$compileDir = $this->app->getOption('SMARTY_COMPILE_DIR');
    		if ($compileDir) return $compileDir;

    		return $this->app->OCTOPUS_PRIVATE_DIR . 'smarty';
    	}

        $compileDir = get_option('SMARTY_COMPILE_DIR');
        if ($compileDir) return $compileDir;

        $compileDir = get_option('OCTOPUS_PRIVATE_DIR');
        if ($compileDir) return $compileDir . 'smarty';

        return sys_get_temp_dir();

    }

    public function getTemplateDir() {

    	// Allow passing template_dir option to app ctor
    	if ($this->app && ($dir = $this->app->getSetting('template_dir'))) {
    		return array($dir);
    	}

    	$siteDir = $this->app ? $this->app->SITE_DIR : get_option('SITE_DIR');
    	$octopusDir = $this->app ? $this->app->OCTOPUS_DIR : get_option('OCTOPUS_DIR');

    	$result = array();

    	$result[] = $siteDir . 'views';
    	if ($this->theme) $result[] = $siteDir . 'themes/' . $this->theme;

    	$result[] = $octopusDir . 'views';
    	if ($this->theme) $result[] = $octopusDir . 'themes/' . $this->theme;

    	return $result;
    }

    /**
     * @deprecated No longer supported. Use Octopus_Smarty::trusted() and
     * Octopus_Smarty::untrusted() and just trust that they are doing the
     * right thing.
     */
    public function reset() {
    }

    /**
     * @return String The results of rendering the given smarty template file.
     */
    public function render($templateFile, $data = array()) {
    	$template = $this->createTemplate($templateFile, $data);
    	return $tpl->fetch();
    }

    /**
     * @return Object A Smarty instance for rendering trusted content.
     */
    public static function trusted() {
    	return self::getInstance(self::$trusted, array('Octopus_Smarty', 'applyTrustedSecurity'));
    }

    /**
     * @return Object A smarty instance for rendering untrusted content.
     */
    public static function untrusted() {
    	return self::getInstance(self::$untrusted, false);
    }

    /**
     * @deprecated Use Octopus_Smarty::trusted() or Octopus_Smarty::untrusted()
     */
    public static function singleton() {

		// NOTE: Because there is existing code that monkeys around with the
		// internal state of the Smarty instance, any code calling
		// Octopus_Smarty::singleton() gets their own Smarty instance to
		// screw around with, leaving the ::trusted() instance pristine.

        return self::getInstance(self::$default, array('Octopus_Smarty', 'applyTrustedSecurity'));
    }

    /**
     * Helper that returns an Octopus_Smarty instance, using a
     * previously-created one if possible.
     */
    private static function getInstance(&$instance, $secureFunc) {

    	$app = class_exists('Octopus_App') && Octopus_App::isStarted() ? Octopus_App::singleton() : null;
		$debug = $app ? $app->DEV : get_option('DEV');
		$theme = $app ? $app->getTheme() : '';

    	if ($instance) {

    		// We've already created an instance, so see if it matches the
    		// parameters being requested

    		if ($instance->debug == $debug &&
    		    $instance->theme == $theme &&
    		    $instance->secureFunc == $secureFunc &&
    		    $instance->app === $app) {
    		    return $instance;
    		}

    	}

    	// We need to create a fresh smarty instance matching the parameters
    	// that have been requested
    	$instance = new Octopus_Smarty($app, $debug, $theme, $secureFunc);

    	return $instance;
    }

    private function createSmartyInstance() {

    	$instance = new Smarty();

    	// TODO: Ditch this
    	$instance->allow_php_tag = true;

    	$instance->compile_dir = $this->getCompileDir();
    	$instance->template_dir = $this->getTemplateDir();

        // Load our smarty plugins before the built-in ones so that
        // we can override some of them
        $instance->plugins_dir = array(
            OCTOPUS_DIR . 'externals/smarty/plugins/',
            dirname(__FILE__) . '/Smarty-' . SMARTY_VERSION . '/libs/plugins/',
        );

    	if ($this->debug) {

        	$instance->error_reporting = E_ALL & ~E_NOTICE;
            $instance->_file_perms = 0666;
            $instance->_dir_perms = 0777;
            $instance->compile_error = true;
            $instance->debugging = true;

    	} else {

    		$instance->error_reporting = E_ERROR;

    	}

    	if ($this->secureFunc) {
			call_user_func($this->secureFunc, $instance);
		}

		return $instance;
    }

    private static function applyTrustedSecurity(Smarty $instance) {

    	$sec = new Smarty_Security($instance);

        // allow all php functions and modifiers
        $sec->php_functions = array();
        $sec->php_modifiers = array();

        $instance->enableSecurity($sec);
    }

}

?>
