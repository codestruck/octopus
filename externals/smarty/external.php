<?php

/**
 * Smarty version # used by Octopus.
 */
define('OCTOPUS_SMARTY_VERSION', '3.0.8');

/**
 * The subdirectory under /_private in which Octopus tells smarty to compile its
 * build files. If for some reason you change something smarty-related (e.g.,
 * significantly alter a custom plugin or something) such that all Smarty build
 * files need to be recreated, you should increment this.
 */
define('OCTOPUS_SMARTY_COMPILE_SUBDIR', 'smarty/' . OCTOPUS_SMARTY_VERSION . '-0');

require_once(dirname(__FILE__) . '/Smarty-' . OCTOPUS_SMARTY_VERSION . '/libs/Smarty.class.php');

/**
 * Wrapper around a Smarty instance. Use Octopus_Smarty::trusted() to get an
 * instance for rendering trusted content and Octopus_Smarty::untrusted() to
 * get an instance for rendering untrusted content.
 */
class Octopus_Smarty {

	// TODO: make this private
    public $smarty;

    private static $trustedInstances = array();
    private static $untrustedInstances = array();
    private static $defaultInstances = array();

    private function __construct($app, $trusted) {
    	$this->smarty = self::createSmartyInstance($app, $trusted);
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
        		$found = $opt . 'views/' . $file;

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
    	return $template->fetch();
    }

    /**
     * @return Object A Smarty instance for rendering trusted content.
     */
    public static function trusted() {
    	return self::getInstance(self::$trustedInstances, null, true);
    }

    /**
     * @deprecated Use Octopus_Smarty::trusted() or Octopus_Smarty::untrusted()
     */
    public static function singleton() {

		// NOTE: Because there is existing code that monkeys around with the
		// internal state of the Smarty instance, any code calling
		// Octopus_Smarty::singleton() gets their own Smarty instance to
		// screw around with, leaving the ::trusted() instance pristine.

    	return self::getInstance(self::$defaultInstances, null, true);
    }

    /**
     * @return Object A smarty instance for rendering untrusted content.
     */
    public static function untrusted() {
    	return self::getInstance(self::$untrustedInstances, null, false);
    }

    private static function applyTrustedSecurity(Smarty $instance) {

    	$sec = new Smarty_Security($instance);

        // allow all php functions and modifiers
        $sec->php_functions = array();
        $sec->php_modifiers = array();

        $instance->enableSecurity($sec);
    }

    private static function buildDirectoryList($app) {

    	if ($app) {
    		$getter = array($app, 'getOption');
    	} else if (function_exists('get_option')) {
    		$getter = 'get_option';
    	} else {
    		throw new Octopus_Exception('Neither Octopus_App or get_option are available.');
    	}

    	$dirs = array(
    		'SITE_DIR' => '',
    		'OCTOPUS_DIR' => '',
    		'OCTOPUS_PRIVATE_DIR' => '',
    		'SMARTY_COMPILE_DIR' => '',
    		'template_dir' => '',
    	);
    	foreach($dirs as $key => $dir) {
    		$dirs[$key] = call_user_func($getter, $key);
    	}

    	if (!$dirs['SMARTY_COMPILE_DIR']) {

    		if ($dirs['OCTOPUS_PRIVATE_DIR']) {
	    		$dirs['SMARTY_COMPILE_DIR'] = $dirs['OCTOPUS_PRIVATE_DIR'] . OCTOPUS_SMARTY_COMPILE_SUBDIR;
	    	} else {
	    		$dirs['SMARTY_COMPILE_DIR'] = sys_get_temp_dir();
	    	}
    	}

    	return $dirs;
    }

    /**
     * @param Octopus_App|null $app
     * @param Boolean $trusted
     * @return Smarty
     */
    private static function createSmartyInstance($app, $trusted) {

    	$instance = new Smarty();

    	// Build an index of directory locations
    	$dirs = self::buildDirectoryList($app);

    	// TODO: Ditch this, even for trusted instances.
    	$instance->allow_php_tag = $trusted;

        // Load our smarty plugins before the built-in ones so that
        // we can override some of them

    	$thisDir = dirname(__FILE__);

        $instance->plugins_dir = array(
            $thisDir . '/plugins/',
            $thisDir . '/Smarty-' . OCTOPUS_SMARTY_VERSION . '/libs/plugins/',
        );


        // NOTE: Previously, it was possible to configure a Smarty instance
        // in 'debug' mode. In practice, this didn't seem to be very useful,
        // so that functionality has been removed.

        /*
    	if ($debug) {

    		$instance->error_reporting = E_ALL & ~E_NOTICE;
            $instance->_file_perms = 0666;
            $instance->_dir_perms = 0777;
            $instance->compile_error = true;
            $instance->debugging = true;

    	}
    	*/

		$instance->error_reporting = E_ERROR;

    	$instance->compile_dir = $dirs['SMARTY_COMPILE_DIR'];
    	$instance->template_dir = self::getTemplateDir($dirs, $trusted);

    	if ($trusted) {
    		self::applyTrustedSecurity($instance);
    	}

		return $instance;
    }

    private static function getInstance(Array &$cache, $app, $trusted) {

    	// This is all to support the notion that you could potentially
    	// (i.e., in tests) have more than one app instance using a smarty
    	// renderer, and each smarty renderer could potentially be configured
    	// differently per-app.

    	if (!$app && class_exists('Octopus_App') && Octopus_App::isStarted()) {
    		$app = Octopus_App::singleton();
    	}

    	$key = $app ? spl_object_hash($app) : 0;

    	if (isset($cache[$key])) {
    		// already have an instance.
    		return $cache[$key];
    	}

    	$instance = new Octopus_Smarty($app, $trusted);

    	// TODO Listen for $app stopping and remove cached instance when that
    	// happens.

    	return ($cache[$key] = $instance);

    }

    private static function getTemplateDir($dirs, $trusted) {

    	// Allow passing template_dir option to the constructor of Octopus_App.
    	// This functionality is used by SoleCMS.
    	if ($dirs['template_dir']) {
    		return array($dirs['template_dir']);
    	}

    	$result = array(
    		$dirs['SITE_DIR'] . 'views',
    		$dirs['SITE_DIR'] . 'themes',
    		$dirs['OCTOPUS_DIR'] . 'views',
    		$dirs['OCTOPUS_DIR'] . 'themes'
    	);

    	if ($trusted) {

    		// For trusted smarty, allow anything in the site dir or octopus dir
    		$result[] = $dirs['SITE_DIR'];
    		$result[] = $dirs['OCTOPUS_DIR'];

    	}

    	return $result;

    }

}
