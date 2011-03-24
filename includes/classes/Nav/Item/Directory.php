<?php

SG::loadClass('SG_Nav_Item');
SG::loadClass('SG_Nav_Item_File');

class SG_Nav_Item_Directory extends SG_Nav_Item {

    public static $defaults = array(
        'filter' => '/\.(php|html?)$/i'
    );

    private $_fullDirectories = array();
    private $_directoryNames = array();
    private $_fsChildren = null;

    public function __construct($options = null) {
        parent::__construct($options);

        if ($options && isset($options['directory'])) {
            $this->addDirectory($options['directory']);
        }
    }

    public function addDirectory($path) {

        if (is_array($path)) {
            foreach($path as $p) {
                $this->addDirectory($p);
            }
        }

        $path = rtrim($path, '/');
        $this->_fullDirectories[$path] = array();
        $this->_directoryNames[] = basename($path);
    }

    protected function getDefaultText() {

        $dir = $this->_directoryNames[0];

        $text = basename($dir);
        $text = preg_replace('/\..*?$/', '', $text);
        $text = preg_replace('/[_-]/', ' ', $text);
        $text = preg_replace('/\s{2,}/', ' ', $text);
        $text = ucwords($text);

        return $text;
    }

    public function matchesPath($path) {

        foreach($this->_directoryNames as $f) {
            if ($f == $path) {
                return true;
            }
        }

        return false;

    }

    protected function &getFileSystemChildren() {

        if ($this->_fsChildren) {
            return $this->_fsChildren;
        }

        $this->_fsChildren = array();

        foreach($this->_fullDirectories as $path => &$items) {

            if (empty($items)) {

                $filter = $path . '/*';
                foreach(glob($filter) as $file) {

                    $item = new SG_Nav_Item_File($file);
                    $items[$item->getPath()] = $item;
                }

            }

            foreach($items as $item) {
                $this->_fsChildren[$item->getPath()] = $item;
            }

        }


        return $this->_fsChildren;
    }

    /**
     * @return Array the children of this item (all the files in the
     * directory + anything added to this item).
     */
    public function getChildren() {

        $children = parent::getChildren();
        $fsChildren = $this->getFileSystemChildren();

        $all = array_merge($fsChildren, $children);
        return $all;
    }

    public function getFile() {
        foreach($this->_fullDirectories as $path => &$items) {
            return $path . '/index.php';
        }
    }

}

?>
