<?php

SG::loadClass('SG_Nav_Item');
SG::loadClass('SG_Nav_Item_Directory');
SG::loadClass('SG_Nav_Item_Controller');

/**
 * Class that manages navigation structure and routing for the app.
 */
class SG_Nav {

    private $_root = null; // Item representing the root
    private $_aliases = array();

    public function __construct($options = null) {
        $this->_root = new SG_Nav_Item_Directory(null, $this);
    }

    /**
     * Adds one or more items to the nav.
     */
    public function &add($options, $text = null) {
        $item = $this->_root->add($options, $text);
        return $item;
    }

    /**
     * Adds nav entries for any controllers discovered in the given directory.
     * @param $dir Mixed Either a string or an array of strings, directory(ies)
     * to scan for controllers.
     * @param $options Array Any extra options. Mostly used for testing. You
     * can override CONTROLLERS_DIR and SITE_CONTROLLERS_DIR by setting those
     * keys here.
     */
    public function &addControllers($dir = null, $options = null) {

        if ($dir === null) {

            $controllersDir = false;
            $siteControllersDir = false;

            if (defined('CONTROLLERS_DIR')) {
                $controllersDir = CONTROLLERS_DIR;
            }

            if (defined('SITE_CONTROLLERS_DIR')) {
                $siteControllersDir = SITE_CONTROLLERS_DIR;
            }

            if ($options !== null) {

                if (isset($options['CONTROLLERS_DIR'])) $controllersDir = $options['CONTROLLERS_DIR'];
                if (isset($options['SITE_CONTROLLERS_DIR'])) $controllersDir = $options['SITE_CONTROLLERS_DIR'];

            }

            $dir = array($siteControllersDir, $controllersDir);

        } else if (!is_array($dir)) {
            $dir = array($dir);
        }

        foreach($dir as $d) {

            if ($d) {
                $this->addControllersFromDirectory($d, $options);
            }

        }

        return $this;
    }

    private function addControllersFromDirectory($dir, $options) {

        $dir = rtrim($dir, '/') . '/';
        foreach(glob($dir . '*.php') as $file) {

            $parts = explode('_', strtolower(basename($file, '.php')));

            $controller = array_pop($parts);
            $controllerItem = new SG_Nav_Item_Controller($controller, $file);

            $parent = $this->_root;

            if (!empty($parts)) {
                $prefixPath = implode('/', $parts);
                $parent = $this->_root->find($prefixPath);
                if (!$parent) $parent = $this->_root->add($prefixPath);
            }

            $parent->add($controllerItem);
        }

    }

    public function &addFromArray($ar) {
        $this->_root->addFromArray($ar);
        return $this;
    }

    /**
     * Adds a directory of content at the root of the nav. Any files in this
     * directory will show up at the root level.
     */
    public function &addRootDirectory($path) {
        $this->_root->addDirectory($path);
        return $this;
    }

    /**
     * Finds the nav item to use for the given path.
     */
    public function &find($path, $options = null) {

        $path = trim($path, '/');
        $pathLen = strlen($path);

        foreach($this->_aliases as $newPath => $oldPath) {

            if ($newPath == $path) {
                $path = $oldPath;
                break;
            }
        }

        // HACK: special case '/'
        if ($path == '') return $this->_root;

        $item = $this->_root->find($path, $options);
        return $item;
    }

    /**
     * Adds an alias to the nav.
     */
    public function &alias($oldPath, $newPath) {

        $oldPath = trim($oldPath, '/');
        $newPath = trim($newPath, '/');

        $this->_aliases[$newPath] = $oldPath;
        return $this;
    }

    public function toHtml() {
        return $this->_root->toHtml();
    }

    public function __toString() {
        return $this->toText();
    }

    public function toText() {
        return $this->_root->toText();
    }

}

?>
