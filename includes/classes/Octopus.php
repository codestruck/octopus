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
     * Binds a class to another class or another class instance.
     * @param string $from The class name to be bound.
     * @param mixed $to Either the class to bin $from to, or an object that
     * should be returned by calls to ::create() for $from.
     */
    public static function bind($from, $to) {

        if (isset(self::$bindings[$from])) {
            array_unshift(self::$bindings[$from], $to);
        } else {
            self::$bindings[$from] = array($to);
        }

    }

    /**
     * Undoes a call to bind().
     */
    public static function unbind($name, $class = null) {

        if (!isset(self::$bindings[$name])) {
            return;
        }

        if ($class === null) {
            unset(self::$bindings[$name]);
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
     * @param String $name The name of the class to create.
     * @param Array $args Arguments to pass to the class's constructor.
     * @return Object A class instance.
     * @throws Octopus_Exception if $name is bound to an object instance and
     * $args is non-empty.
     */
    public static function create($name, Array $args = array()) {

        $binding = self::getBinding($name);

        if (is_object($binding)) {

            if (!$args) {
                // $name was bound to a specific object.
                return $binding;
            } else {
                throw new Octopus_Exception("Cannot specify constructor arguments for class bound to object instance.");
            }

        }

        switch(count($args)) {

            // Don't use reflection for 99.999999% of cases
            case 0: return new $binding();
            case 1: return new $binding($args[0]);
            case 2: return new $binding($args[0], $args[1]);
            case 3: return new $binding($args[0], $args[1], $args[2]);
            case 4: return new $binding($args[0], $args[1], $args[2], $args[3]);
            case 5: return new $binding($args[0], $args[1], $args[2], $args[3], $args[4]);
            // case 6: die("You need to refactor your shit, yo.");

            default:
                $r = new ReflectionClass($binding);
                return $r->newInstanceArgs($args);
        }
    }

    /**
     * Gets the class or object that is bound to the given name.
     */
    public static function getBinding($name) {

        if (!empty(self::$bindings[$name])) {
            return self::$bindings[$name][0];
        }

        return $name;
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

