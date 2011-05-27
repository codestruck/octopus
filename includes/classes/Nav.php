<?php

Octopus::loadClass('Octopus_Nav_Item');
Octopus::loadClass('Octopus_Nav_Item_Directory');

/**
 * Class that manages navigation structure and routing, and page options for
 * the app.
 */
class Octopus_Nav {

    private $_root = null; // Item representing the root
    private $_aliases = array();

    public function __construct($options = null) {
        $this->_root = new Octopus_Nav_Item_Directory(null, $this);
    }

    /**
     * Adds one or more items to the nav.
     */
    public function &add($options, $text = null) {
        $item = $this->_root->add($options, $text);
        return $item;
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

        $path = $this->resolve($path);

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

    /**
     * Looks for any aliases and returns the actual path that should be used
     * for the given path.
     */
    public function resolve($path) {

        $path = trim($path, '/');

        foreach($this->_aliases as $newPath => $oldPath) {

            if ($newPath == $path) {
                return $oldPath;
            }
        }

        return $path;
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
