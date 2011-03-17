<?php

    /**
     * A single item in a nav hierarchy.
     */
    abstract class SG_Nav_Item {

        static $_registry = array();

        var $_parent;
        var $_nav;
        var $_findCache = null;

        /**
         * Creates a new nav item.
         * @param $nav object The SG_Nav instance this item is working for.
         * @param $options array Custom options for this item.
         */
        function __construct($nav, $parent, $options = null) {
            $this->_nav = $nav;
        }

        /**
         * Factory method for creating an appropriate SG_Nav_Item instance
         * based on the options passed in.
         */
        function create($options, $extra = null) {

            if (is_string($options)) {
                $options = array('path' => $options);
            }

            if (is_string($extra)) {
                $options['text'] = $extra;
            }

        }

        /**
         * Registers an SG_Nav_Item subclass.
         */
        function register($class) {
            self::$_registry[] = $class;
        }


        /**
         * Finds the first item under this one that matches the given path,
         * or returns false if not found.
         */
        function &find($path, $options = null) {

            if ($this->_checkFindResultCache($path, $options, $item)) {
                return $item;
            }

            if (is_string($path)) {
                $path = explode('/', trim($path, '/'));
            }

            foreach($this->_children as $child) {

                if ($child->matchesPath($path, $options)) {

                    if ($options === null) {
                        $this->_cacheFindResult($path, $options, $item);
                    }

                    return $child;
                }

            }

            array_shift($path);

            if (!empty($path)) {

                foreach($this->_children as $child) {

                    if ($grandchild = $child->find($path, $options)) {
                        return $this->_cacheFindResult($path, $grandchild);
                    }

                }

            }

            return false;

        }

        /**
         * @return Array The children of this item.
         */
        function getChildren() {
            return array();
        }

        /**
         * @return bool Whether this item should be used for $path.
         */
        function matchesPath($path) {
            return false;
        }

        /**
         * Stores the given item in the find cache.
         * @return object $item
         */
        function &_cacheFindResult($path, &$item, &$options) {

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
        function &_checkFindResultCache($path, &$options, &$item) {

            if (empty($this->_findCache)) {
                $item = null;
                return false;
            }

            $path = (is_array($path) ? implode('/', $path) : $path;

            if (isset($this->_findCache[$path])) {
                $item = $this->_findCache[$path];
                return $item;
            }

            return false;
        }

    }

?>
