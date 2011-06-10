<?php

Octopus::loadClass('Octopus_Dispatcher');

/**
 * Class encapsulating an HTTP request.
 */
class Octopus_Request {

    private $app;
    private $path;
    private $resolvedPath;
    private $controllerInfo = null;
    private $controllerClass = null;

    public function __construct($app, $path, $resolvedPath = null, $options = null) {
        $this->app = $app;
        $this->path = trim($path);
        $this->resolvedPath = ($resolvedPath === null ? $this->path : trim($resolvedPath));
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
        return $this->getControllerInfo('action');
    }

    /**
     * @return String The action that was in the actual request (i.e., if
     * there wasn't an action, getAction() will return "index" but this will
     * return "".
     */
    public function getRequestedAction() {
        return $this->getControllerInfo('original_action');
    }

    /**
     * @return Array Arguments to pass to the action for this request.
     */
    public function getActionArgs() {
        return $this->getControllerInfo('args');
    }

    /**
     * @return String The class of controller to use for this request, or
     * false if none is found.
     */
    public function getControllerClass() {

        if ($this->controllerClass !== null) {
            return $this->controllerClass;
        }

        $info = $this->getControllerInfo();
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
        return $this->getControllerInfo('file');
    }

    private function getControllerInfo($key = null) {

        if ($this->controllerInfo === null) {

            $this->controllerInfo = Octopus_Dispatcher::findController(
                $this->getResolvedPath(),
                $this->app
            );

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
     * @return Object A reference to the current HTTP request being processed.
     */
    public static function current() {
        $app = Octopus_App::singleton();
        return $app->getCurrentRequest();
    }
}

?>
