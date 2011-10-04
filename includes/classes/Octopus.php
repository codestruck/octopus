<?php

Octopus::loadClass('Octopus_Exception');
Octopus::loadClass('Base');

/**
 * Class locator and loader.
 */
class Octopus {

    private static $_bindings = array();
    private static $_externals = array();

    /**
     * Binds a new class to a name, to help support the IoC pattern.
     * @param String $class The custom class to bind.
     * @param String $name The name to which to bind it. This should be
     * the Octopus class name minus the initial 'Octopus_'.
     */
    public static function bind($name, $class) {
        if (isset(self::$_bindings[$name])) {
            array_unshift($class, self::$_bindings[$name]);
        } else {
            self::$_bindings[$name] = array($class);
        }
    }

    /**
     * Undoes a call to bind().
     */
    public static function unbind($name, $class) {
        if (!isset(self::$_bindings[$name])) {
            return;
        }

        $newBindings = array();
        foreach(self::$_bindings[$name] as $boundClass) {
            if ($class !== $boundClass) {
                $newBindings[] = $boundClass;
            }
        }

        self::$_bindings[$name] = $newBindings;
    }

    /**
     * Creates a new instance of the class with the given name.
     * @param String $role The name of the class to create. Should be
     * the standard octopus class name minus the 'Octopus_', e.g.
     * 'Controller', 'Html_Element'.
     * @param Array $args Arguments to pass to the class's constructor.
     * @return Object A class instance.
     */
    public static function create($name, Array $args = array()) {

        $class = self::getClass($name);
        self::loadClass($class);

        switch(count($args)) {

            // Don't use reflection for 99.999999% of cases
            case 0: return new $class();
            case 1: return new $class($args[0]);
            case 2: return new $class($args[0], $args[1]);
            case 3: return new $class($args[0], $args[1], $args[2]);
            case 4: return new $class($args[0], $args[1], $args[2], $args[3]);
            case 5: return new $class($args[0], $args[1], $args[2], $args[3], $args[4]);
            // case 6: die("You need to refactor your shit, yo.");

            default:
                $r = new ReflectionClass($class);
                return $r->newInstanceArgs($args);
        }
    }

    /**
     * Gets the class that is bound to the given name.
     */
    public static function getClass($name) {

        $class = 'Octopus_' . $name;

        if (!empty(self::$_bindings[$name])) {
            $class = self::$_bindings[$name][0];
        }
        return $class;
    }

    /**
     * Locates the given class and makes it available.
     * @param $classname String The class to find and load.
     * @param $errorWhenMissing bool Whether or not to crap out when the class
     * isn't found.
     * @return bool True if class was found and loaded, false otherwise.
     */
    public static function loadClass($classname, $exceptionWhenMissing = true) {

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

            if ($exceptionWhenMissing) {
                throw new Octopus_Exception("Could not load class: $classname");
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
