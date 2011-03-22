<?php

SG::loadClass('SG_Nav_Item');

/**
 * Class that manages navigation structure and routing for the app.
 */
class SG_Nav {

    private $_root = null; // Item representing the root
    private $_maps = array();

    public function __construct($options = null) {
        $this->_root = new SG_Nav_Item();
    }

    /**
     * Adds one or more items to the nav.
     */
    public function &add($options, $text = null) {
        $item = $this->_root->add($options, $text);
        return $item;
    }

    /**
     * Finds the nav item to use for the given path.
     */
    public function &find($path, $options = null) {

        if (isset($this->_maps[$path])) {
            $path = $this->_maps[$path];
        }

        // HACK: special case '/'
        if ($path == '/') return $this->_root;

        $item = $this->_root->find($path, $options);
        return $item;
    }

    /**
     * Adds an alias to the nav.
     */
    public function &map($path, $toPath) {
        $this->_maps[$path] = $toPath;
        return $this;
    }

}

?>
