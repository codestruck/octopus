<?php

require_once(dirname(__FILE__) . '/Smarty-3.0.8/libs/Smarty.class.php');

class Octopus_Smarty extends Octopus_Base {

    public $smarty;

    protected function __construct($templateDir = null, $compileDir = null) {

        $app = (class_exists('Octopus_App') && Octopus_App::isStarted()) ? Octopus_App::singleton() : null;

        if ($templateDir == null) {

            if (defined('SITE_DIR')) {
                $templateDir = SITE_DIR . 'views/';
            } else if ($app) {
                $templateDir = $app->getOption('SITE_DIR') . 'views/';
            }

        }

        if ($compileDir == null) {

            if (defined('SMARTY_COMPILE_DIR')) {
                $compileDir = SMARTY_COMPILE_DIR;
            } else if (defined('OCTOPUS_PRIVATE_DIR')) {
                $compileDir = OCTOPUS_PRIVATE_DIR . 'smarty';
            } else if ($app) {

                $compileDir = $app->getOption('SMARTY_COMPILE_DIR');
                if (!$compileDir) {
                    $compileDir = $app->getOption('OCTOPUS_PRIVATE_DIR') . 'smarty';
                }

            } else {
                $compileDir = '/tmp';
            }

        }


        $this->smarty = new Smarty();
        $this->smarty->error_reporting = E_ALL & ~E_NOTICE;
        $this->smarty->template_dir = $templateDir;
        $this->smarty->compile_dir = $compileDir;
        $this->smarty->allow_php_tag = true;

        if (DEV) {
            $this->smarty->_file_perms = 0666;
            $this->smarty->_dir_perms = 0777;
        }

    }

    public static function &singleton() {
        return Octopus_Base::base_singleton('Octopus_Smarty');
    }


}

?>
