<?php

require_once(dirname(__FILE__) . '/Smarty-3.0.8/libs/Smarty.class.php');

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
                $templateDir = get_option('SITE_DIR') . 'views';
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
        $this->smarty->error_reporting = E_ALL & ~E_NOTICE;
        $this->smarty->template_dir = $templateDir;
        $this->smarty->compile_dir = $compileDir;
        $this->smarty->allow_php_tag = true;

        // custom plugin dir
        $this->smarty->plugins_dir = array(
            OCTOPUS_DIR . 'externals/smarty/plugins/',
            dirname(__FILE__) . '/Smarty-3.0.8/libs/plugins/',
        );

        // allow all php functions and modifiers
        $security_policy = new Smarty_Security($this->smarty);
        $security_policy->php_functions = array();
        $security_policy->php_modifiers = array();
        $this->smarty->enableSecurity($security_policy);

        if (DEV) {
            $this->smarty->_file_perms = 0666;
            $this->smarty->_dir_perms = 0777;
            $this->smarty->compile_error = true;
            $this->smarty->debugging = true;
        }


    }

    public static function &singleton() {
        return Octopus_Base::base_singleton('Octopus_Smarty');
    }


}

?>
