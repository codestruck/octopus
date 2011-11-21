<?php

/**
 * Class that manages app settings.
 */
class Octopus_Settings extends Octopus_Base implements Iterator, ArrayAccess {

    private $_keys = array();
    private $_values = array();
    private $_loaded = false;

    private $_iteratorArray = null;
    private $_iteratorKey = null;

    /**
     * @param $settings Array Array of setting definitions.
     * @see addFromArray
     */
    public function __construct($settings = null) {

        if (!empty($settings)) {
            $this->addFromArray($settings);
        }

    }

    /**
     * Adds setting definitions from a file.
     */
    public function addFromFile($file) {

        if (preg_match('/\.yaml$/i', $file)) {
            $this->addFromYaml(file_get_contents($file));
        /*
        } else if (preg_match('/\.php$/i', $file)) {
           $this->addFromPHP($file);
        */
        } else {
            die("Can't load settings from file: $file");
        }

        return $this;
    }

    /**
     * Adds setting data from a YAML string.
     */
    public function addFromYaml($yaml) {

        Octopus::loadExternal('spyc_yaml');
        $data = load_yaml($yaml);

        return $this->addFromArray($data);
    }

    public function addFromArray($ar) {

        foreach($ar as $name => $options) {

            $found = false;

            foreach($this->_keys as $kname => $key) {

                if ($key->matches($name)) {

                    $found = true;
                    $key->overlay($name, $options);

                }

            }

            if (!$found) {
                $key = new Octopus_Settings_Key($name, $options);
                $this->_keys[$name] = $key;
            }
        }

        return $this;
    }

    /**
     * @return Mixed An Octopus_Html_Element for editing the given setting, or
     * false if it is not a valid setting.
     */
    public function createEditor($setting) {

        $key = $this->getKey($setting);

        if (!$key) {
            return false;
        }

        return $key->createEditor();
    }

    /**
     * @return Mixed The present value of the given setting.
     */
    public function get($name) {

        $key = $this->getKey($name, true);

        $this->loadFromDB();

        return $key->getValue($name, $this->_values);
    }

    /**
     * @param $name String The name of the key to retrieve. Case-sensitive.
     * @param $createIfMissing bool Whether, if this setting does not exist,
     * to create it.
     * @return Mixed An Octopus_Settings_Key instance for the given setting
     * if it exists, otherwise false.
     */
    public function getKey($name, $createIfMissing = false) {

        if (isset($this->_keys[$name])) {
            return $this->_keys[$name];
        }

        foreach($this->_keys as $kname => $key) {
            if ($key->matches($name)) {
                return $key;
            }
        }

        if (!$createIfMissing) {
            return false;
        }

        $key = new Octopus_Settings_Key($name);
        $this->_keys[$name] = $key;

        return $key;
    }

    /**
     * Resets a single setting to its default value.
     */
    public function reset($name) {

        $key = $this->getKey($name, false);

        if (!$key) $key = new Octopus_Settings_Key($name);

        $key->remove($name, $this->_values);

        return $this;
    }

    /**
     * Forces settings to be reloaded from the DB.
     */
    public function reload() {
        $this->_values = array();
        $this->_loaded = false;
        return $this;
    }

    /**
     * Resets all settings to their original state.
     */
    public function resetAll() {

        $this->_values = array();
        $this->_loaded = false;

        $d = new Octopus_DB_Delete();
        $d->table('settings');
        $d->execute();

        return $this;
    }

    /**
     * @param $name Mixed Either a setting name, or an array of key/value pairs.
     * @param $value Mixed If $name is a string, the corresponding value, otherwise, ignored.
     * @return Object $this, for method chaining.
     */
    public function set($name, $value = null) {

        if (is_array($name)) {
            $values =& $name;
        } else {
            $values = array($name => $value);
        }

        foreach($values as $name => $value) {
            $key = $this->getKey($name, true);
            $key->set($name, $value, $this->_values);
        }

        return $this;
    }

    /**
     * @return Array An array of all present settings.
     */
    public function toArray() {

        $defaults = array();
        foreach($this->_keys as $name => $key) {
            $defaults[$name] = $key->getDefaultValue($name);
        }

        $this->loadFromDB();

        $result = array_merge($defaults, $this->_values);
        ksort($result);

        return $result;
    }

    private function loadFromDB() {

        if ($this->_loaded) return;

        try {
            $s = new Octopus_DB_Select();
            $s->table('settings', array('name', 'value'));
            $this->_values = $s->getMap();
            $this->_loaded = true;
        } catch(Octopus_DB_Exception $ex) {

            // If there is no DB configuration, fall back
            $this->_values = array();
            $this->_loaded = false;

        }

    }

    /* Iterator Implementation {{{ */

    public function current() {
        return $this->_iteratorKey ? $this->get($this->_iteratorKey->name) : null;
    }

    public function key() {
        return $this->_iteratorKey ? $this->_iteratorKey->name : null;
    }

    public function next() {
        $this->loadIteratorArray();
        $this->_iteratorKey = array_shift($this->_iteratorArray);
    }

    public function rewind() {
        $this->_iteratorKey = $this->_iteratorArray = null;
    }

    public function valid() {
        $this->loadIteratorArray();
        return $this->_iteratorKey !== null || !empty($this->_iteratorArray);
    }

    private function loadIteratorArray() {
        if ($this->_iteratorArray === null) {
            $this->_iteratorArray = $this->_keys;
            $this->_iteratorKey = array_shift($this->_iteratorArray);
        }
    }

    /* End Iterator Implementation }}} */

    /* ArrayAccess Implementation */

    public function offsetExists($offset) {

    	return !!$this->getKey($offset, false);

    }

    public function offsetGet($offset) {
    	return $this->get($offset);
    }

    public function offsetSet($offset, $value) {
    	return $this->set($offset, $value);
    }

    public function offsetUnset($offset) {
    	$this->reset($offset);
    }

    public static function singleton() {
        return Octopus_App::singleton()->getSettings();
    }

}

?>
