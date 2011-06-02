<?php

Octopus::loadClass('Octopus_Exception');
Octopus::loadClass('Base');

/**
 * Class locator.
 */
class Octopus {

    private static $_externals = array();

    /**
     * Locates the given class and makes it available.
     * @param $classname String The class to find and load.
     * @param $errorWhenMissing bool Whether or not to crap out when the class
     * isn't found.
     * @return bool True if class was found and loaded, false otherwise.
     */
    public static function loadClass($classname, $errorWhenMissing = true) {

        if (class_exists($classname)) {
            return true;
        }

        $classname = str_replace('Octopus_', '', $classname);

        $filedir = str_replace('_', DIRECTORY_SEPARATOR, $classname);
        $file = $filedir . '.php';

        $dirs = array(dirname(__FILE__) . '/');

        if (defined('SITE_DIR')) {
            array_unshift($dirs, SITE_DIR . 'classes/');
        }

        $filepath = get_file($file, $dirs);

        if (!$filepath) {

            if ($errorWhenMissing) {
                trigger_error("Octopus::loadClass('$classname') - class not found", E_USER_WARNING);
            }

            return false;
        }

        require_once($filepath);
        return true;
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

        if (class_exists('Octopus_App') && Octopus_App::isStarted()) {
            $dir = Octopus_App::singleton()->getOption('OCTOPUS_EXTERNALS_DIR');
        } else if (defined('OCTOPUS_EXTERNALS_DIR')) {
            $dir = OCTOPUS_EXTERNALS_DIR;
        }

        $EXTERNAL_DIR = "{$dir}{$name}/";

        $file = "{$EXTERNAL_DIR}external.php";
        if (!is_file($file)) {
            $file = ROOT_DIR . 'site/externals/' . $name . '/external.php';
        }

        require_once($file);

        $func = "external_{$name}";
        if (function_exists($func)) {
            $func($version);
        }
    }

    /**
     * Makes a model class available.
     */
    public static function loadModel($classname) {

        $classname = start_in('Octopus_Model_', $classname);

        if (!class_exists($classname)) {

            $filedir = str_replace('Octopus_Model_', '', $classname);
            $file = $filedir . '.php';

            $dirs = array(OCTOPUS_DIR . 'models/');

            if (defined('SITE_DIR')) {
                array_unshift($dirs, SITE_DIR . 'models/');
                array_unshift($dirs, SITE_DIR . 'includes/models/');
            }

            $filepath = get_file($file, $dirs);

            if (!$filepath) {
                trigger_error("Octopus::loadModel('$classname') - class not found", E_USER_WARNING);
            }

            require_once($filepath);

        }

    }

    /**
     * Loads a controller. Searches the site dir first, then the octopus dir.
     */
    public static function loadController($name) {

        $classname = $name;
        if (!preg_match('/Controller$/', $classname)) {
            $classname .= 'Controller';
            if (class_exists($classname)) {
                return false;
            }
        }

        $filename = 'controllers/' . preg_replace('/Controller$/', '', $name) . '.php';
        $dirs = array();

        if (defined('SITE_DIR')) {
            $dirs[] = SITE_DIR;
        }

        $dirs[] = OCTOPUS_DIR;

        $path = get_file($filename, $dirs);

        if (!$path) {
            trigger_error("Octopus::loadController('$name') - Controller not found", E_USER_WARNING);
        }

        require_once($path);

        return true;

    }

}

?>
