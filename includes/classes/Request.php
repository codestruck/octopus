<?php

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
    private $controller = null;

    public function __construct(Octopus_App $app, $path, $resolvedPath = null, $options = array()) {

        $defaults = array(
            'get_data_file' => 'php://input',
            'post_data_file' => 'php://input',
            'put_data_file' => 'php://input',
            'delete_data_file' => 'php://input',
        );

        $this->app = $app;
        $this->options = array_merge($defaults, $options);

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

    /**
     * @return Octopus_Controller The controller instance associated with
     * this request.
     */
    public function getController() {

        if ($this->controller) {
            return $this->controller;
        }

        $this->controller = $this->createController();

        if (!$this->controller) {
            $this->controller = $this->createDefaultController();
        }

        return $this->controller;
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

    public function isPut() {
        return $this->getMethod() == 'put';
    }

    public function isDelete() {
        return $this->getMethod() == 'delete';
    }

    public function getInputData() {
        if (is_json_content_type()) {
            return $this->getJsonInputData();
        }

        $method = $this->getMethod();
        switch ($method) {
            case 'get':
                return $_GET;
                break;
            case 'post':
                return $_POST;
                break;
            case 'put':
            case 'delete':
                parse_str(file_get_contents($this->options[$method . '_data_file']), $data);
                return $data;
                break;
        }

        return array();
    }

    public function getJsonInputData() {
        $method = $this->getMethod();
        $filename = $this->options[$method . '_data_file'];

        // php://input cannot be tested with is_file
        if ($filename !== 'php://input' && !is_file($filename)) {
            return array();
        }

        $str = file_get_contents($filename);
        $json = json_decode($str, true);
        return $json;
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

        // NOTE: For a request like '/test' that resolves to the DefaultController,
        // original_action is null. So we substitute the resolved action.

        $result = $this->internalGetControllerInfo('original_action');
        if ($result) return $result;

        if ($this->isDefaultController()) {
            return $this->getAction();
        }

        return null;
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

        Octopus::requireOnce($info['file']);

        foreach($info['potential_names'] as $class) {
            if (class_exists($class)) {
                return $this->controllerClass = $class;
            }
        }

        return $this->controllerClass = false;
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
     * @return Boolean Whether this request is getting sent to the app's
     * DefaultController
     */
    public function isDefaultController() {
        return $this->getControllerClass() === 'DefaultController';
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
     * @return Object An Octopus_Controller if found, otherwise NULL.
     */
    protected function createController() {
        $class = $this->getControllerClass();
        return $class ? new $class() : null;
    }

    /**
     * Creates an instance of the octopus DefaultController.
     */
    protected function createDefaultController() {
        Octopus::requireOnce($this->app->getOption('OCTOPUS_DIR') . 'controllers/Default.php');
        return new DefaultController();
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

        // No controller was found. Use the DefaultController
        $result = $this->getDefaultController($pathParts);

        return $result;
    }

    private function &getDefaultController(&$pathParts) {

        $app = $this->app;

        $result =  array(
                'potential_names' => array('DefaultController'),
                'action' => $pathParts ? array_shift($pathParts) : '',
                'args' => $pathParts ? $pathParts : array()
            );

        $siteControllersDir = $app->getSetting('SITE_DIR') . 'controllers/';

        if (is_file($siteControllersDir . 'Default.php')) {
            $result['file'] = $siteControllersDir . 'Default.php';
        } else {
            $result['file'] = $app->getSetting('OCTOPUS_DIR') . 'controllers/Default.php';
        }

        return $result;
    }

    private static function buildListOfControllerLocations(&$pathParts, &$seps, &$underscoreParts) {

        /*
         * Places a controller for the path '/my/really-fun/controller' can be:
         *
         *  My/ReallyFun/Controller.php
         *  my/really_fun/Controller.php
         *  my/really_fun/controller.php
         *  my/reallyfun/controller.php
         *  My_ReallyFun_Controller.php
         *  my_really_fun_controller.php
         *  myreallyfuncontroller.php
         *  my_really_fun_Controller.php
         *
         */

        $underscoreParts =  array_map('underscore', $pathParts);
        $camelCaseParts =   array_map('camel_case_with_initial_capital', $underscoreParts);
        $lowerCaseParts =   array_map('strtolower', $camelCaseParts);
        $comboParts = false;

        $partCount = count($pathParts);

        if ($partCount > 1) {

            // Allow e.g. api/1/Controller.php in addition to
            // Api/1/Controller.php

            $comboParts = array_slice($underscoreParts, 0, $partCount - 1);
            $comboParts[] = $camelCaseParts[$partCount - 1];
        }

        $toTry = array();

        foreach($seps as $sep) {

            if ($comboParts) {
                $toTry[implode($sep, $comboParts)] = true;
            }

            $toTry[implode($sep, $camelCaseParts)] = true;
            $toTry[implode($sep, $underscoreParts)] = true;
            $toTry[implode($sep, $lowerCaseParts)] = true;
        }

        return $toTry;
    }

    private static function scanForControllerFile($dir, $seps, &$pathParts, &$potentialNames) {

        $underscoreParts = null;

        $toTry = self::buildListOfControllerLocations($pathParts, $seps, $underscoreParts);
        $potentialNames = array();

        foreach($toTry as $name => $unused) {

            $file = $dir . $name . '.php';

            if (is_file($file)) {

                // Found it!
                $fullName = empty($pathParts) ? $name : implode(' ', $pathParts);
                $fullName = preg_replace('#[\s-_/]+#', ' ', $fullName);
                $fullName = ucwords($fullName);

                $underscoredFullName = str_replace(' ', '_', $fullName);
                $camelFullName = str_replace(' ', '', $fullName);

                $potentialNames[$underscoredFullName . 'Controller'] = true;
                $potentialNames[$underscoredFullName . '_Controller'] = true;
                $potentialNames[$camelFullName . 'Controller'] = true;

                // Last portion of controller can be used as the name, e.g.
                // you could have a controller in /some/crazy/deep/path.php
                // be called 'PathController', rather than
                // 'SomeCrazyDeepPathController'
                $potentialNames[camel_case(array_pop($underscoreParts), true) . 'Controller'] = true;

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
                    if ($action !== null) array_unshift($args, $action);
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
