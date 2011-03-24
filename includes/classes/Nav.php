<?php

SG::loadClass('SG_Nav_Item');
SG::loadClass('SG_Nav_Item_Directory');

/**
 * Class that manages navigation structure and routing for the app.
 */
class SG_Nav {

    private $_root = null; // Item representing the root
    private $_aliases = array();

    public function __construct($options = null) {
        $this->_root = new SG_Nav_Item_Directory();
    }

    /**
     * Adds one or more items to the nav.
     */
    public function &add($options, $text = null) {
        $item = $this->_root->add($options, $text);
        return $item;
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

            $newLen = strlen($newPath);

            if ($newLen > $pathLen) {
                continue;
            }

            if (strncmp($newPath, $path, $newLen) == 0) {
                // we have an alias
                $path = $oldPath . '/' . substr($path,$newLen);
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

}

?>
