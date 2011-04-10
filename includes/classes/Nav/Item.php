<?php

/**
 * A single item in a nav hierarchy.
 */
class SG_Nav_Item {

    private static $_registry = array();

    protected $_parent = null;
    protected $_nav = null;
    protected $_findCache = null;
    private $_children = array();
    private $_propagate = array();

    /**
     * Creates a new nav item.
     * @param $nav object The SG_Nav instance this item is working for.
     * @param $parent object The SG_Nav_Item that is above this one in the
     * hierarchy.
     * @param $options array Custom options for this item.
     */
    public function __construct($options = null) {

        $options = $options ? $options : array();
        if (isset(self::$defaults)) {
            $options = array_merge($options, self::$defaults);
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

        if (isset($options['regex'])) {
            $options['type'] = 'regex';
        }

        if (!isset($options['type'])) {

            if (isset($options['directory'])) {
                $options['type'] = 'directory';
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
    public static function register($name, $class) {
        self::$_registry[$name] = $class;
    }

    /**
     * Adds one or more items underneath this item.
     */
    public function &add($options, $extra = null) {

        // TODO: detect if $options is a candidate for addFromArray and use that

        $fullPath = '';

        if (is_string($options)) {

            if (is_array($extra)) {
                $p = $options;
                $options = $extra;
                $options['path'] = $p;
            } else {
                $options = array('path' => $options);
            }
        }

        if (is_array($options)) {

            if (isset($options['path'])) {
                $options['path'] = trim($options['path'], '/');
                $fullPath = $options['path'];
            }

            if (is_string($extra)) $options['text'] = $extra;
        }

        if (empty($fullPath)) {
            $item = $this->internalAdd($options, $extra);
            return $item;
        }

        $levels = explode('/', $fullPath);

        $item = $this;

        while($levelCount = count($levels)) {

            $level = array_shift($levels);

            if ($levelCount == 1) {
                // This is the last one so really add.
                $options['path'] = $level;
                $item = $item->internalAdd($options, $extra);
                break;
            }

            $child = $item->find($level);
            if (!$child) $child = $item->internalAdd($level);

            $item = $child;
        }

        return $item;
    }

    /**
     * Attempts to load up the nav with items from an array.
     */
    public function addFromArray($ar) {

        $count = 0;

        foreach($ar as $key => $value) {
            $count += $this->addSingleItemFromArray($key, $value);
        }

        return $count;

    }

    private function addSingleItemFromArray($key, $options) {

        if (!is_array($options)) {

            if (is_string($key)) {
                $options = array('path' => $key, 'text' => $options);
            } else {
                $options = array('path' => $options);
            }

        }

        if (is_array($options)) {

            if (!isset($options['path'])) $options['path'] = $key;

        }

        $count = 0;

        $children = isset($options['children']) ? $options['children'] : null;
        unset($options['children']);

        $alias = isset($options['alias']) ? $options['alias'] : null;
        unset($options['alias']);

        $item = $this->add($options);
        if ($alias) $this->getNav()->alias($item->getFullPath(), $alias);
        $count++;

        if (!empty($children)) {

            foreach($children as $key => $child) {
                $count += $item->addSingleItemFromArray($key, $child);
            }

        }

        return $count;
    }

    /**
     * Adds a single item.
     */
    protected function &internalAdd($options, $extra = null) {

        if ($item = $this->applyOptionsToExistingItem($options, $extra)) {
            return $item;
        }

        $item = ($options instanceof SG_Nav_Item) ? $options : SG_Nav_Item::create($options, $extra);
        $item->setParent($this);
        $this->_children[] = $item;
        $this->invalidateCaches();

        return $item;
    }

    /**
     * Attempts to modify an existing item rather than adding a new one.
     * @return Mixed modified item or false if nothing is done.
     */
    protected function applyOptionsToExistingItem($options, $extra) {

        if (!is_array($options)) {
            return false;
        }

        // We might be able to overlay these options on an existing item

        $path = isset($options['path']) ? $options['path'] : false;
        if (!$path) return false;

        $existing = null;
        foreach($this->getChildren() as $c) {
            if ($c->getPath() == $path) {
                $existing = $c;
                break;
            }
        }

        if ($existing) {

            $type = isset($options['type']) ? $options['type'] : false;
            $existingType = isset($existing->options['type']) ? $existing->options['type'] : false;

            if ($type == $existingType) {
                $existing->options = array_merge($existing->options, $options);
                $existing->invalidateCaches();
                return $existing;
            }
        }

        return false;
    }

    /**
     * Finds the first item under this one that matches the given path,
     * or returns false if not found.
     */
    public function &find($path, $options = null) {

        $item = false;
        $path = trim($path, '/');

        if ($this->_checkFindResultCache($path, $options, $item)) {
            $item = $item->getFindResult($path);
            return $item;
        }

        $item = $this->internalFind($path, $options);
        $this->_cacheFindResult($path, $options, $item);

        return $item;
    }

    /**
     * Does a real find, without any caching.
     */
    protected function &internalFind($path, $options) {

        $item = false;

        list($firstPart, $remainingPath) = $this->splitPath($path);

        if (!$firstPart) {
            $item = false;
            return $item;
        }

        $haveMorePath = strlen($remainingPath) > 0;
        $matchesFullPath = false;

        foreach($this->getChildren() as $child) {

            // Since regex items can have '/' in their pattern, first try matching
            // the complete path, then just the next portion of it.

            if ($child->matchesPath($path, $options)) {
                $matchesFullPath = true;
                $item = $child;
                break;
            }

            if ($haveMorePath) {

                if ($child->matchesPath($firstPart, $options)) {
                    $item = $child;
                    break;
                }

            }
        }

        if ($item) {

            if ($matchesFullPath) {
                $item = $item->getFindResult($path);
                return $item;

            } else {

                // We need to search deeper
                $item = $item->find($remainingPath, $options);

            }

        }

        return $item;
    }

    /**
     * @return The value of a named component of this item's path.
     */
    public function getArg($name, $default = null) {
        // The real implementation is in SG_Nav_Item_Regex and SG_Nav_Item_Action
        return $default;
    }

    /**
     * @return Mixed The value of the given option for this item. If the option
     * is not explicitly set on this item, we traverse up the hierarchy to
     * find it elsewhere.
     */
    public function getOption($name, $default = null) {

        return $this->internalGetOption($name, $default, false);

    }

    /**
     * Internal function that children call on their parents to get the value
     * of an option. Handles propagation.
     */
    protected function internalGetOption($name, $default, $respectPropagation) {

        if (isset($this->options[$name])) {

            if ($respectPropagation && isset($this->_propagate[$name]) && !$this->_propagate[$name]) {
                return $default;
            }

            return $this->options[$name];
        } else {

            $parent = $this->getParent();
            if ($parent) {
                return $parent->internalGetOption($name, $default, true);
            }

        }

        return $default;

    }


    /**
     * Sets the value of the given option.
     * @return Object $this for method chaining.
     */
    public function &setOption($name, $value, $propagate = true) {

        $this->options[$name] = $value;
        $this->_propagate[$name] = $propagate;

        return $this;
    }

    /**
     * Splits $path into an array whose first element is the first component
     * of the given path (the text up until the first '/') and second component
     * is the rest of the path.
     */
    protected function splitPath($path) {

        $pos = strpos($path, '/');
        if ($pos === false) {
            return array($path, '');
        }

        return array(substr($path, 0, $pos), trim(substr($path, $pos + 1), '/'));
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
     * @return Object An SG_Nav_Item instance specific to the given path. In
     * most cases, this will be $this. Some subclasses that do virtual path
     * mapping might return a specific SG_Nav_Item for that path.
     */
    protected function &getFindResult($path) {
        return $this;
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
        return isset($this->options['path']) ? $this->options['path'] : '';
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

        $default = str_replace('_', ' ', $this->getPath());
        $default = ucwords($default);
        return $default;

    }


    /**
     * @return bool Whether this item should be used for $path.
     */
    public function matchesPath($path) {
        return strcmp($path, $this->getPath()) == 0;
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

        // TODO actually implement caching once everything is working
        $item = false;
        return $item;

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

    public function getArgs() {
        return array();
    }

    public function getNav() {

        if ($this->_nav) {
            return $this->_nav;
        }

        $parent = $this->getParent();
        if ($parent) {
            return $this->_nav = $parent->getNav();
        } else {
            return null;
        }
    }

    protected function setParent($parent) {
        $this->_parent = $parent;
        $this->_nav = null;
    }

    public function &getParent() {
        return $this->_parent;
    }

    /**
     * Override to clear any caches this item is holding on to.
     */
    protected function invalidateCaches() {
        $this->_findCache = array();
    }

    public function __toString() {
        return $this->toText();
    }

    public function toText($indent = 0) {

        $text = $this->getText();
        $path = $this->getPath();
        $fullPath = $this->getFullPath();
        $s = '';

        if ($fullPath != '') {
            $s .= str_repeat('  ', $indent);
            $s .= "$text ($path; $fullPath)";
        }

        foreach($this->getChildren() as $child) {
            $s .= "\n" . $child->toText($indent+1);
        }

        return $s;

    }

    public function toHtml() {

        $html = '<ul>';

        foreach($this->getChildren() as $c) {
            $text = h($c->getText());
            $path = h(u($c->getFullPath()));
            $html .= <<<END
<li>
    <a href="$path">$text</a>
END;

            $html .= $c->toHtml();
            $html .= '</li>';
        }
        $html .= '</ul>';

        return $html;
    }

}

SG_Nav_Item::register('regex', 'SG_Nav_Item_Regex');
SG_Nav_Item::register('directory', 'SG_Nav_Item_Directory');
SG_Nav_Item::register('file', 'SG_Nav_Item_File');

?>
