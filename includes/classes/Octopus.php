<?php

if (!spl_autoload_register(array('Octopus', 'autoLoadClass'))) {
    die("Failed to register autoloader.");
}

/**
 * Class locator and loader.
 */
class Octopus {

    private static $bindings = array();
    private static $externals = array();

    private static $classDirs = array();
    private static $controllerDirs = array();

    // YAY WE GET TO KEEP OUR OWN LIST OF WHAT FILES WE'VE REQUIRE_ONCE'D!!!
    // See the note on requireOnce() for why this exists
    private static $alreadyLoaded = array();

    /**
     * Adds a directory to be scanned for classes.
     */
    public static function addClassDir($dir, $prepend = false) {
        if ($prepend) {
        	array_unshift(self::$classDirs, $dir);
        } else {
        	array_push(self::$classDirs, $dir);
        }
    }

    public static function removeClassDir($dir) {
        $index = array_search($dir, self::$classDirs);
        if ($index !== false) {
        	unset(self::$classDirs[$index]);
        }
    }

    /**
     * Adds a directory to be scanned for controllers.
     */
    public static function addControllerDir($dir, $prepend = false) {
        if ($prepend) {
        	array_unshift(self::$controllerDirs, $dir);
        } else {
        	array_push(self::$controllerDirs, $dir);
        }
    }

    public static function removeControllerDir($dir) {
        $index = array_search($dir, self::$controllerDirs);
        if ($index !== false) {
        	unset(self::$controllerDirs[$index]);
        }
    }

    public static function autoLoadClass($class) {
        self::loadClass($class, false, false, false);
    }

    /**
     * Binds a new class to a name, to help support the IoC pattern.
     * @param String $class The custom class to bind.
     * @param String $name The name to which to bind it. This should be
     * the Octopus class name minus the initial 'Octopus_'.
     */
    public static function bind($name, $class) {
        if (isset(self::$bindings[$name])) {
            array_unshift($class, self::$bindings[$name]);
        } else {
            self::$bindings[$name] = array($class);
        }
    }

    /**
     * Undoes a call to bind().
     */
    public static function unbind($name, $class) {
        if (!isset(self::$bindings[$name])) {
            return;
        }

        $newBindings = array();
        foreach(self::$bindings[$name] as $boundClass) {
            if ($class !== $boundClass) {
                $newBindings[] = $boundClass;
            }
        }

        self::$bindings[$name] = $newBindings;
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

        if (!empty(self::$bindings[$name])) {
            $class = self::$bindings[$name][0];
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
    public static function loadClass($class, $exceptionWhenMissing = true, $checkExists = true, $debug = false) {

        if ($checkExists && class_exists($class)) {
            return true;
        }

        $class = preg_replace('/^Octopus_/', '', $class);
        $file = str_replace('_', DIRECTORY_SEPARATOR, $class) . '.php';

        $tried = $debug ? array() : null;

        foreach(self::$classDirs as $dir) {
            $f = $dir . $file;
            if ($debug) $tried[] = $f;
            if (is_file($f)) {
                self::requireOnce($f);
                return true;
            }
        }

        if (preg_match('/Controller$/', $class)) {

            if (self::loadController($class, $exceptionWhenMissing, $checkExists, $debug)) {
                return true;
            }

        }

        if ($exceptionWhenMissing) {
            throw new Octopus_Exception("Could not load class: $class");
        }

        if ($debug) {
            $exists = array_map('is_file', $tried);
            dump_r($class, $tried, $exists);
        }

        return false;
    }

    /**
     * Attempts to load a controller class.
     * @param $class Name of the controller, with or without 'Controller' at
     * the end.
     * @return true if loaded, false otherwise.
     */
    public static function loadController($class, $exceptionWhenMissing = true, $checkExists = true, $debug = false) {

        $class = preg_replace('/_*Controller$/', '', $class);

        if ($checkExists && class_exists($class)) {
            return true;
        }

        $tries = $debug ? array() : null;

        $file = $class . '.php';
        foreach(self::$controllerDirs as $dir) {

            $f = $dir . $file;
            if ($debug) $tries[] = $f;
            if (is_file($f)) {
                self::requireOnce($f);
                return true;
            }

        }

        if ($exceptionWhenMissing) {
            throw new Octopus_Exception("Could not load controller: $class");
        }

        if ($debug) {
            dump_r($class, $tries);
        }

        return false;
    }

    /**
     * Includes an external library.
     */
    public static function loadExternal($name, $version = null) {

        $name = strtolower($name);

        if (isset(self::$externals[$name])) {
            return;
        }
        self::$externals[$name] = true;

        // First, look in site dir
        foreach(array('SITE_DIR', 'OCTOPUS_DIR') as $key) {

            $dir = get_option($key);
            if (!$dir || !is_dir($dir)) {
            	continue;
            }

            $externalsDir = $dir . "externals/{$name}/";
            $file = $externalsDir . 'external.php';

            if (is_file($file)) {
            	self::loadExternalFile($name, $file, $version);
            	return true;
            }

        }

        throw new Octopus_Exception("External not found: $name");
    }

    /**
     * @deprecated
     */
    public static function loadModel() {}

    /**
     * A require_once wrapper. Ok, look: I know this looks insane, but
     * require_once + class autoloading + case-insensitive filesystems = insanity.
     * But really, don't use this. It's used internally to support autoloading
     * and controller discovery. Use require_once. I don't care.
     * @param String $__file The file to require
     * @param Mixed $__vars any variables to make available before requiring
     * the file.
     */
    public static function requireOnce($__file, $__vars = null) {

        $__normalFile = strtolower($__file);
        if (isset(self::$alreadyLoaded[$__normalFile])) {
        	return;
        }

        self::$alreadyLoaded[$__normalFile] = true;
        unset($__normalFile);

        if ($__vars) {
	        extract($__vars);
	    }
	    unset($__vars);

        require_once($__file);
    }

    private static function loadExternalFile($name, $file, $version) {

    	self::requireOnce($file, array('EXTERNAL_DIR' => dirname($file) . '/'));
    	self::callExternalFunction($name, $version);

    }

    private static function callExternalFunction($external, $version) {
        $func = 'external_' . $external;
        if (function_exists($func)) {
        	$func($version);
        }
    }
}

Octopus::addClassDir(dirname(__FILE__) . '/');

?>