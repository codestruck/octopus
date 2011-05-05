<?php


/**
 * Octopus_Nav_Item that uses a regular expression to match its path.
 */
class Octopus_Nav_Item_Regex extends Octopus_Nav_Item {

    private $_original = null;
    private $_path = null;
    private $_matches = null;

    public function __construct($options, $original = null, $path = null, $matches = null) {
        parent::__construct($options);
        $this->_original = $original;
        $this->_path = $path;
        $this->_matches = $matches;
    }

    public function &add($options, $extra = null) {

        $result = $this->call('add', $options, $extra);
        return $result;

    }

    public function &find($path, $options = null) {
        $result = $this->call('find', $path, $options);
        return $result;
    }

    public function getChildren() {
        return $this->call('getChildren', $path, $options);
    }

    public function getArg($name, $default = null) {

        if (empty($this->_matches)) {
            return parent::getArg($name, $default);
        }

        if (isset($this->_matches[$name])) {
            return $this->_matches[$name];
        } else {
            return $default;
        }

    }

    public function getFile() {

        $file = parent::getFile();

        if (empty($this->_matches) || empty($file)) {
            return $file;
        }

        foreach($this->_matches as $name => $text) {
            $file = str_replace('{' . $name . '}', $text, $file);
        }

        return $file;

    }

    public function getRegex() {
        if ($this->_original) {
            return $this->_original->getRegex();
        } else {
            return $this->options['regex'];
        }
    }

    public function matchesPath($path) {
        return preg_match($this->getRegex(), $path);
    }

    protected function &getFindResult($path) {

        /*
         * Some functions, like getFile(), need to know what path was matched
         * when find() was called in order to work properly. So we return a
         * new item per-call to find(), which falls back to the this item
         * for traversing and manipulating the hierarchy.
         *
         */

        preg_match($this->getRegex(), $path, $matches);
        $result = new Octopus_Nav_Item_Regex($this->options, $this, $path, $matches);
        return $result;
    }

    protected function setParent($parent) {
        return $this->call('setParent', $parent);
    }

    public function &getParent() {
        $parent = $this->call('getParent');
        return $parent;
    }

    public function getPath() {
        return $this->_path;
    }

    private function call($func, $a = null, $b = null, $c = null, $d = null) {

        if ($this->_original) {
            $result = $this->_original->$func($a, $b, $c, $d);
        } else {
            $result = parent::$func($a, $b, $c, $d);
        }

        return $result;

    }


}
