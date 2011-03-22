<?php

/**
 * A single item in a nav hierarchy.
 */
class SG_Nav_Item {

    public static $defaults = array();

    private static $_registry = array();

    protected $_parent = null;
    protected $_nav = null;
    protected $_findCache = null;
    private $_children = array();

    /**
     * Creates a new nav item.
     * @param $nav object The SG_Nav instance this item is working for.
     * @param $parent object The SG_Nav_Item that is above this one in the
     * hierarchy.
     * @param $options array Custom options for this item.
     */
    public function __construct($options = null) {

        $options = $options ? $options : array();
        if (isset(static::$defaults)) {
            $options = array_merge($options, static::$defaults);
        }

        $this->options = $options;
    }

    /**
     * Factory method for creating an appropriate SG_Nav_Item instance
     * based on the options passed in.
     */
    public static function create($options, $extra = null) {

        if (is_string($options)) {
            $options = array('path' => $options);
        }

        if (is_string($extra)) {
            $options['text'] = $extra;
        }

        if (!isset($options['type'])) {

            if (isset($options['directory'])) {
                $options['type'] = 'SG_Nav_Item_Directory';
            }

        }

        if (isset($options['type'])) {
            $class = self::$_registry[$options['type']];
        } else {
            $class = 'SG_Nav_Item';
        }

        SG::loadClass($class);
        return new $class($options);
    }

    /**
     * Registers an SG_Nav_Item subclass.
     */
    public static function register($class) {
        self::$_registry[] = $class;
    }

    /**
     * Adds one or more items underneath this item.
     */
    public function &add($options, $extra = null) {

        $path = '';

        if (is_string($options)) {
            $path = $options;
        } else if (is_array($options)) {
            $path = $options['path'];
        }

        $levels = explode('/', $path);
        $parent = $this;

        while(count($levels)) {

            $path = array_shift($levels);

            if (empty($levels)) {
                $parent->add($options, $extra);
                break;
            }

            $child = $parent->find($path);
            if (!$child) $child = $parent->add($path);

            $parent = $child;
        }


        $this->

        return $item;
    }

    /**
     * Adds a single item.
     */
    protected function internalAdd($options) {
        $item = is_array($options) ? SG_Nav_Item::create($options) : $item;
        $this->_children[] = $item;
        return $item;
    }

    /**
     * Finds the first item under this one that matches the given path,
     * or returns false if not found.
     */
    public function &find($path, $options = null) {

        $item = false;
        $originalPath = $path;

        if ($this->_checkFindResultCache($path, $options, $item)) {
            return $item;
        }

        $pathParts = is_array($path) ? $path : explode('/', trim($path, '/'));

        $firstPart = array_shift($pathParts);

        foreach($this->getChildren() as $child) {

            if ($child->matchesPath($firstPart, $options)) {
                $this->_cacheFindResult($originalPath, $options, $child);
                $item = $child;
                break;
            }

        }

        if (!$item || empty($pathParts)) {
            return $item;
        }

        $item = $child->find($pathParts, $options);
        $this->_cacheFindResult($originalPath, $options, $item);


        return $item;

    }

    /**
     * @return Array The children of this item.
     */
    public function getChildren() {
        return $this->_children;
    }

    /**
     * @return string The full path to the PHP file that should be included for
     * this item.
     */
    public function getFile() {

        if (isset($this->options['file'])) {
            return $this->options['file'];
        }

        return false;
    }

    /**
     * @return string Full path from the root.
     */
    public function getFullPath() {

        $full = '';
        $p = $this->getParent();
        if ($p) $full = $p->getFullPath();

        $full .= ($full == '' ? '' : '/') . $this->getPath();

        return $full;
    }

    /**
     * @return String Path to this item, relative to its parent.
     */
    public function getPath() {
        return $this->options['path'];
    }

    /**
     * @return String The text to display in menus for this item.
     */
    public function getText() {

        if (isset($this->options['text'])) {
            return $this->options['text'];
        } else if (isset($this->options['title'])) {
            return $this->options['title'];
        }

        return $this->options['text'] = $this->getDefaultText();
    }

    /**
     * @return String The page title for this item, if different from
     * getText().
     */
    public function getTitle() {

        if (isset($this->options['title'])) {
            return $this->options['title'];
        } else if (isset($this->options['text'])) {
            return $this->options['text'];
        }

        return $this->options['title'] = $this->getDefaultText();
    }

    /**
     * Place for subclasses to generate the default text/title for this item.
     * See, for example, SG_Nav_Item_File.
     */
    protected function getDefaultText() {
        return '(unnamed)';
    }


    /**
     * @return bool Whether this item should be used for $path.
     */
    public function matchesPath($path) {
        return $this->options['path'] == $path;
    }

    /**
     * Stores the given item in the find cache.
     * @return object $item
     */
    protected function &_cacheFindResult($path, &$item, &$options) {

        // For now, don't cache if any custom options were used for the lookup
        if ($options !== null) {
            return $item;
        }

        if (!$this->_findCache) {
            $this->_findCache = array();
        }

        $this->_findCache[is_array($path) ? implode('/', $path) : $path] = $item;
        return $item;
    }

    /**
     * Checks to see if the given path has been looked up before, and
     * returns the corresponding item if so.
     */
    protected function &_checkFindResultCache($path, &$options, &$item) {

        $item = false;

        if (empty($this->_findCache)) {
            return $item;
        }

        $path = (is_array($path) ? implode('/', $path) : $path);

        if (isset($this->_findCache[$path])) {
            $item = $this->_findCache[$path];
            return $item;
        }

        return $item;
    }

}

?>
