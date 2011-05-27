<?php

/**
 * Class encapsulating an HTTP request.
 */
class Octopus_Request {

    private $_path;
    private $_resolvedPath;

    public function __construct($path, $resolvedPath = null, $options = null) {
        $this->_path = trim($path);
        $this->_resolvedPath = ($resolvedPath === null ? $this->_path : trim($resolvedPath));
    }

    /**
     * @return String The requested path.
     */
    public function getPath() {
        return $this->_path;
    }

    /**
     * @return String The actual path the requested path resolved to.
     */
    public function getResolvedPath() {
        return $this->_resolvedPath;
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
