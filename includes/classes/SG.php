<?php

class SG {

    private static $_externals = array();

    public static function loadClass($classname) {

        if (!class_exists($classname)) {

            $class_dir = dirname(__FILE__) . '/';
            $classname = str_replace('SG_', '', $classname);

            $filedir = str_replace('_', DIRECTORY_SEPARATOR, $classname);
            $file = $filedir . '.php';

            if (is_file($class_dir . $file)) {
                require_once($class_dir. $file);
            } else {
                trigger_error("SG::loadClass('$classname') - class not found", E_USER_WARNING);
            }
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


/*
    function loadModel($classname, $module = null) {

        $classname = start_in('SG_Model_', $classname);

        if (!class_exists($classname)) {

            $filedir = str_replace('SG_Model_', '', $classname);
            $file = $filedir . '.php';

            if ($module) {
                if (is_file(MODULE_DIR . $module . '/models/' . $file)) {
                    require_once(MODULE_DIR . $module . '/models/' . $file);
                    return;
                }
            }

            if (file_exists(SG_LOAD_CUSTOM_MODEL_DIR . $file)) {
                require_once(SG_LOAD_CUSTOM_MODEL_DIR . $file);
                return;
            }

            trigger_error("SG::loadModel('$classname') - class not found", E_USER_WARNING);

        }

    }
*/

}

SG::loadClass('SG_Base');

?>
