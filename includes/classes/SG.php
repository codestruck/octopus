<?php

class SG {

    private static $_externals = array();

    public static function loadClass($classname) {

        if (!class_exists($classname)) {

            $classname = str_replace('SG_', '', $classname);

            $filedir = str_replace('_', DIRECTORY_SEPARATOR, $classname);
            $file = $filedir . '.php';

            $dirs = array(dirname(__FILE__) . '/');

            if (defined('SITE_DIR')) {
                array_unshift($dirs, SITE_DIR . 'classes/');
            }

            $filepath = get_file($file, $dirs);

            if (!$filepath) {
                trigger_error("SG::loadClass('$classname') - class not found", E_USER_WARNING);
            }

            require_once($filepath);

        }

    }

    /**
     * Includes an external library.
     */
    public static function loadExternal($name, $version = null) {

        $name = strtolower($name);

        if (isset(self::$_externals[$name])) {
            return;
        }
        self::$_externals[$name] = true;

        $dir = '';

        if (defined('EXTERNALS_DIR')) {
            $dir = EXTERNALS_DIR;
        } else if (class_exists('SG_App') && SG_App::isStarted()) {
            $dir = SG_App::singleton()->getOption('EXTERNALS_DIR');
        }

        $EXTERNAL_DIR = "{$dir}{$name}/";

        $file = "{$EXTERNAL_DIR}external.php";
        require_once($file);

        $func = "external_{$name}";
        if (function_exists($func)) {
            $func($version);
        }
    }

    function loadModel($classname) {

        $classname = start_in('SG_Model_', $classname);

        if (!class_exists($classname)) {

            $filedir = str_replace('SG_Model_', '', $classname);
            $file = $filedir . '.php';

            $dirs = array(OCTOPUS_DIR . 'models/');

            if (defined('SITE_DIR')) {
                array_unshift($dirs, SITE_DIR . 'models/');
            }

            $filepath = get_file($file, $dirs);

            if (!$filepath) {
                trigger_error("SG::loadModel('$classname') - class not found", E_USER_WARNING);
            }

            require_once($filepath);

        }

    }

}

SG::loadClass('SG_Base');

?>
