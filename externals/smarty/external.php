<?php

define('SMARTY_VERSION', '3.0.8');
require_once(dirname(__FILE__) . '/Smarty-' . SMARTY_VERSION . '/libs/Smarty.class.php');

class Octopus_Smarty extends Octopus_Base {

    public $smarty;

    private $templateDir, $compileDir;

    protected function __construct($templateDir = null, $compileDir = null) {

        $this->templateDir = $templateDir;
        $this->compileDir = $compileDir;

        $this->reset();
    }

    public function reset() {


        $app = (class_exists('Octopus_App') && Octopus_App::isStarted()) ? Octopus_App::singleton() : null;

        $templateDir = $this->templateDir;
        if ($templateDir == null) {

            if (isset($app->template_dir)) {
                $templateDir = $app->template_dir;
            } else {
                $templateDir = get_option('SITE_DIR') . 'views/';
            }

        }

        $compileDir = $this->compileDir;
        if (!$compileDir) {

            $compileDir = get_option('SMARTY_COMPILE_DIR');

            if (!$compileDir) {
                $compileDir = get_option('OCTOPUS_PRIVATE_DIR') . 'smarty';
            }

            if (!$compileDir) {
                $compileDir = sys_get_temp_dir();
            }

        }

        $this->smarty = new Smarty();
        $this->smarty->error_reporting = E_ERROR;
        $this->smarty->template_dir = $templateDir;
        $this->smarty->compile_dir = $compileDir;
        $this->smarty->allow_php_tag = true;

        // custom plugin dir
        $this->smarty->plugins_dir = array(
            OCTOPUS_DIR . 'externals/smarty/plugins/',
            dirname(__FILE__) . '/Smarty-' . SMARTY_VERSION . '/libs/plugins/',
        );

        // allow all php functions and modifiers
        $security_policy = new Smarty_Security($this->smarty);
        $security_policy->php_functions = array();
        $security_policy->php_modifiers = array();

        $siteDir = get_option('SITE_DIR');
        $theme = get_option('site.theme');
        $octopusDir = get_option('OCTOPUS_DIR');

        $security_policy->secure_dir = array(

        	// Allow smarty views
	        $siteDir . 'views',

	        $octopusDir . 'views',

	        // Allow smarty page templates for the curren theme
	        $siteDir . 'themes/' . $theme . '/templates/html',

	        $octopusDir . 'themes/' . $theme . '/templates/html'

	    );
        $this->smarty->enableSecurity($security_policy);

        if (DEV) {
        	$this->smarty->error_reporting = E_ALL & ~E_NOTICE;
            $this->smarty->_file_perms = 0666;
            $this->smarty->_dir_perms = 0777;
            $this->smarty->compile_error = true;
            $this->smarty->debugging = true;
        }


    }

    /**
     * @return String The results of rendering the given smarty template file.
     */
    public function render($templateFile, $data = array()) {

    	$smarty = $this->smarty;
        $smartyData = $smarty->createData();

        foreach($data as $key => $value) {
            $smartyData->assign($key, $value);
        }

        // For relative paths, go from /views
        if ($templateFile[0] !== '/') {
        	foreach(array('SITE_DIR', 'OCTOPUS_DIR') as $opt) {
        		$file = get_option($opt) . 'views/' . $templateFile;
        		if (is_file($file)) {
        			$templateFile = $file;
        			break;
        		}
        	}
        }

        // Look for templates in the same directory the file is in.
        $smarty->template_dir = array(dirname($templateFile));

        $tpl = $smarty->createTemplate($templateFile, $smartyData);
        return $tpl->fetch();
    }

    public static function &singleton() {
        return Octopus_Base::base_singleton('Octopus_Smarty');
    }


}

?>
