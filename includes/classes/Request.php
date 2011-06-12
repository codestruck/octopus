<?php

Octopus::loadClass('Octopus_Dispatcher');

/**
 * Class encapsulating an HTTP request.
 */
class Octopus_Request {

    private $app;
    private $path;
    private $pathParts;
    private $resolvedPath;
    private $resolvedPathParts;
    private $options;
    private $controllerInfo = null;
    private $controllerClass = null;

    public function __construct($app, $path, $resolvedPath = null, $options = array()) {

        $this->app = $app;
        $this->options = $options;

        $this->cleanPath($path, $this->path, $this->pathParts);

        if ($resolvedPath === null || $resolvedPath != $path) {
            $this->cleanPath($resolvedPath, $this->resolvedPath, $this->resolvedPathParts);
        } else {
            $this->resolvedPath = $this->path;
            $this->resolvedPathParts = $this->pathParts;
        }
    }

    /**
     * @return Octopus_App The app instance that owns this request.
     */
    public function getApp() {
        return $this->app;
    }

    public function getMethod() {
        if (isset($_SERVER['REQUEST_METHOD'])) {
            return strtolower($_SERVER['REQUEST_METHOD']);
        } else {
            return 'get';
        }
    }

    public function isGet() {
        return $this->getMethod() == 'get';
    }

    public function isPost() {
        return $this->getMethod() == 'post';
    }

    /**
     * @return String The action for this request.
     */
    public function getAction() {
        return $this->internalGetControllerInfo('action');
    }

    /**
     * @return String The action that was in the actual request (i.e., if
     * there wasn't an action, getAction() will return "index" but this will
     * return "".
     */
    public function getRequestedAction() {
        return $this->internalGetControllerInfo('original_action');
    }

    /**
     * @return Array Arguments to pass to the action for this request.
     */
    public function getActionArgs() {
        return $this->internalGetControllerInfo('args');
    }

    /**
     * @return String The class of controller to use for this request, or
     * false if none is found.
     */
    public function getControllerClass() {

        if ($this->controllerClass !== null) {
            return $this->controllerClass;
        }

        $info = $this->internalGetControllerInfo();
        if (empty($info) || empty($info['potential_names'])) return false;

        self::requireOnce($info['file']);

        foreach($info['potential_names'] as $class) {
            if (class_exists($class)) {
                return $this->controllerClass = $class;
            }
        }

        return $this->controllerClass = false;
    }

    private static function requireOnce($file) {
        require_once($file);
    }

    /**
     * @return String Full path to the controller file, or false if it can't
     * be found.
     */
    public function getControllerFile() {
        return $this->internalGetControllerInfo('file');
    }

    /**
     * @return Mixed An array with the following keys:
     * <dl>
     *  <dt>file</dt>
     *  <dd>The PHP file the controller is in.</dd>
     *  <dt>potential_names</dt>
     *  <dd>An array of potential class names for the controller.</dd>
     *  <dt>action</dt>
     *  <dd>The action to be executed on the controller</dd>
     *  <dt>original_action</dt>
     *  <dd>The actual action contained in the path (if '', 'index' will be
     *  substituted in the 'action' key</dd>
     *  <dt>args</dt>
     *  <dd>Arguments to be passed to the action</dd>
     * </dl>
     *
     * If no candidate controllers are found, returns false.
     */
    public function getControllerInfo() {
        return $this->internalGetControllerInfo();
    }

    private function internalGetControllerInfo($key = null) {

        if ($this->controllerInfo === null) {

            $this->controllerInfo = $this->findController($this->resolvedPathParts);

        }

        if ($key === null) {
            return $this->controllerInfo;
        }

        return isset($this->controllerInfo[$key]) ? $this->controllerInfo[$key] : null;
    }

    /**
     * @return String The requested path.
     */
    public function getPath() {
        return $this->path;
    }

    /**
     * @return String The actual path the requested path resolved to.
     */
    public function getResolvedPath() {
        return $this->resolvedPath;
    }

    /**
     * Cleans up a path, removing illegal characters, resolving '..' etc.
     */
    protected function cleanPath($path, &$cleaned, &$cleanedParts) {

        $path = preg_replace('/\s+/', '', $path);
        $cleanedParts = array();

        foreach(explode('/', $path) as $part) {
            if (!$part || $part == '.') {
                continue;
            } else if ($part == '..') {
                if ($cleanedParts) {
                    array_pop($cleanedParts);
                }
                continue;
            } else {
                $cleanedParts[] = $part;
            }
        }

        $cleaned = implode('/', $cleanedParts);

        if ($path && substr($path, -1, 1) == '/') {
            $cleaned .= '/';
        }
    }

    /**
     * @return Object A reference to the current HTTP request being processed.
     */
    public static function current() {
        $app = Octopus_App::singleton();
        return $app->getCurrentRequest();
    }

    /**
     * Given a path, figures out the controller to use. This does the heavy
     * lifting for getControllerInfo().
     */
    private function &findController(&$pathParts) {

        $directoriesToSearch = array(
            $this->app->getSetting('SITE_DIR') . 'controllers/',
            $this->app->getSetting('OCTOPUS_DIR') . 'controllers/'
        );

        $file = null;
        $potential_names = null;
        $action = null;
        $args = null;
        $result = false;

        foreach($directoriesToSearch as $dir) {

            if (self::searchForController($dir, array('/','_'), $pathParts, $file, $potential_names, $action, $args)) {

                $original_action = $action;
                if (!$action) {
                    $action = 'index';
                }

                $result = compact('file', 'potential_names', 'action', 'original_action', 'args');
                return $result;

            }

        }

        return $result;
    }

    private static function scanForControllerFile($dir, $seps, &$pathParts, &$potentialNames) {

        $potentialNames = array();

        $usParts = array_map('underscore', $pathParts);

        $camelParts = array();
        foreach($usParts as $p) {
            $camelParts[] = camel_case($p, true);
        }

        $lParts = array_map('strtolower', $camelParts);

        $toTry = array();
        foreach($seps as $sep) {
            $toTry[implode($sep, $usParts)] = true;
            $toTry[implode($sep, $camelParts)] = true;
            $toTry[implode($sep, $lParts)] = true;
        }

        foreach($toTry as $name => $unused) {

            $file = $dir . $name . '.php';
            $file = get_true_filename($file);

            if ($file) {

                // Found it!
                $fullName = empty($pathParts) ? $name : implode(' ', $pathParts);
                $fullName = preg_replace('#[\s-_/]+#', ' ', $fullName);
                $fullName = ucwords($fullName);

                $potentialNames[str_replace(' ', '_', $fullName) . 'Controller'] = true;
                $potentialNames[str_replace(' ', '', $fullName) . 'Controller'] = true;
                $potentialNames[camel_case(array_pop($usParts), true) . 'Controller'] = true;

                $potentialNames = array_keys($potentialNames);

                return $file;
            }

        }

        return false;

    }

    private static function searchForController($dir, $seps, $pathParts, &$file, &$potentialNames, &$action, &$args) {

        $potentialNames = array();
        $args = array();
        $action = null;

        /* Ways controller files can be named:
         *  my_controller.php
         *  MyController.php
         *  mycontroller.php
         */

        while(!empty($pathParts)) {

            $file = self::scanForControllerFile($dir, $seps, $pathParts, $potentialNames);

            if ($file) {
                return true;
            }

            if ($action !== null) {
                array_unshift($args, $action);
            }

            /* This is to support mapping e.g.:
             *      path/to/controller/57
             * to:
             *      path/to/controller/index/57
             *
             * or:
             *      path/to/controller/57/subaction/9
             * to:
             *      path/to/controller/subaction/57/9
             *
             * Since '57' cannot possibly be an action, it gets
             * shuffled over to the args array.
             */

             while($pathParts) {

                $action = array_pop($pathParts);

                if (is_numeric($action)) {
                    array_unshift($args, $action);
                    $action = null;
                }

                $file = self::scanForControllerFile($dir, $seps, $pathParts, $potentialNames);

                if ($file) {
                    return true;
                }

                if (!$pathParts) {
                    return false;
                }

                $nextAction = array_pop($pathParts);
                $file = self::scanForControllerFile($dir, $seps, $pathParts, $potentialNames);

                if ($file) {
                    // This is actually a valid path, sort out action etc
                    if (is_numeric($nextAction)) {
                        array_unshift($args, $nextAction);
                        $nextAction = null;
                    } else {
                        if ($action !== null) array_unshift($args, $action);
                        $action = $nextAction;
                        $nextAction = null;
                    }

                    return true;
                }

                // The remaining path does not refer to a valid controller
                if (is_numeric($nextAction)) {
                    array_unshift($args, $action);
                    array_unshift($args, $nextAction);
                    $action = null;
                } else {
                    array_push($pathParts, $nextAction);
                    break;
                }
            }

        }

        return false;
    }


}

?>
