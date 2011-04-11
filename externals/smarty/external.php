<?php

require_once('Smarty-3.0.7/libs/Smarty.class.php');

class SG_Smarty extends SG_Base {

    public $smarty;

    protected function __construct($templateDir = null, $compileDir = null) {

        $app = (class_exists('SG_App') && SG_App::isStarted()) ? SG_App::singleton() : null;

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
            } else if (defined('PRIVATE_DIR')) {
                $compileDir = PRIVATE_DIR . 'smarty';
            } else if ($app) {

                $compileDir = $app->getOption('SMARTY_COMPILE_DIR');
                if (!$compileDir) {
                    $compileDir = $app->getOption('PRIVATE_DIR') . 'smarty';
                }

            } else {
                $compileDir = '/tmp';
            }

        }


        $this->smarty = new Smarty();
        $this->smarty->template_dir = $templateDir;
        $this->smarty->compile_dir = $compileDir;

    }

    public static function &singleton() {
        return SG_Base::base_singleton('SG_Smarty');
    }


}

?>
