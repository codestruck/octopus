<?php

/**
 * Class that manages app settings.
 */
class SG_Settings extends SG_Base implements Iterator {

    private $_settings = array();
    private $_values = array();
    private $_loaded = false;

    private $_iteratorKeys = null;
    private $_iteratorKey = null;

    /**
     * Adds setting definitions from a file.
     */
    public function addFromFile($file) {

        if (preg_match('/\.yaml/i', $file)) {
            $this->addFromYaml(file_get_contents($file));
        } else {
            die("Can't load settings from file: $file");
        }

        return $this;
    }

    /**
     * Adds setting data from a YAML string.
     */
    public function addFromYaml($yaml) {

        SG::loadExternal('spyc_yaml');
        $data = load_yaml($yaml);

        return $this->addFromArray($data);
    }

    public function addFromArray($ar) {

        $this->_settings = array_merge($this->_settings, $ar);
        return $this;

    }

    /**
     * @return Object An SG_Html_Element for editing the given setting.
     */
    public function createEditor($setting) {

        $options = isset($this->_settings[$setting]) ? $this->_settings[$setting] : array();

        SG::loadClass('SG_Html_Form_Field');
        $field = new SG_Html_Form_Field($setting);

        if (isset($options['desc'])) {
            $field->setLabel($options['desc']);
        } else if (isset($options['label'])) {
            $field->setLabel($options['label']);
        } else {
            $field->setLabel($setting);
        }


        return $field;
    }

    /**
     * @return Mixed The present value of the given setting.
     */
    public function get($name) {

        if (isset($this->_values[$name])) {
            return $this->_values[$name];
        }

        $this->loadFromDB();

        if (isset($this->_values[$name])) {
            return $this->_values[$name];
        }

        $setting =& $this->_settings[$name];
        return isset($setting['default']) ? $setting['default'] : null;
    }

    /**
     * Resets a single setting to its default value.
     */
    public function reset($setting) {

        unset($this->_values[$setting]);

        $d = new SG_DB_Delete();
        $d->table('settings');
        $d->where('name = ?', $setting);
        $d->execute();

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

        $d = new SG_DB_Delete();
        $d->table('settings');
        $d->execute();

        return $this;
    }

    /**
     * @param $name Mixed Either a setting name, or an array of key/value pairs.
     * @param $value Mixed If $name is a string, the corresponding value,
     *   otherwise, ignored.
     * @return Object $this, for method chaining.
     */
    public function set($name, $value = null) {

        if (is_array($name)) {
            $values =& $name;
        } else {
            $values = array($name => $value);
        }

        foreach($values as $key => $value) {

            $this->_values[$key] = $value;

            $d = new SG_DB_Delete();
            $d->table('settings');
            $d->where('name = ?', $key);
            $d->execute();

            $i = new SG_DB_Insert();
            $i->table('settings');
            $i->set('name', $key);
            $i->set('value', $value);
            $i->execute();
        }

        return $this;
    }

    /**
     * @return Array An array of all present settings.
     */
    public function toArray() {

        $defaults = array();
        foreach($this->_settings as $name => $def) {
            $defaults[$name] = isset($def['default']) ? $def['default'] : null;
        }

        $this->loadFromDB();

        $result = array_merge($defaults, $this->_values);
        ksort($result);

        return $result;
    }

    private function loadFromDB() {

        if ($this->_loaded) return;
        $this->_loaded = true;

        $s = new SG_DB_Select();
        $s->table('settings', array('name', 'value'));
        $this->_values = $s->getMap();

    }

    /* Iterator Implementation {{{ */

    public function current() {
        $this->loadIteratorKeys();
        return $this->get($this->_iteratorKey);
    }

    public function key() {
        return $this->_iteratorKey;
    }

    public function next() {
        $this->loadIteratorKeys();
        $this->_iteratorKey = array_shift($this->_iteratorKeys);
    }

    public function rewind() {
        $this->_iteratorKeys =
            $this->_iteratorKey =
                $this->_iteratorValue = null;
    }

    public function valid() {
        $this->loadIteratorKeys();
        return $this->_iteratorKey !== null || !empty($this->_iteratorKeys);
    }

    private function loadIteratorKeys() {
        if ($this->_iteratorKeys === null) {
            $this->_iteratorKeys = array_keys($this->toArray());
            $this->_iteratorKey = array_shift($this->_iteratorKeys);
        }
    }

    /* End Iterator Implementation }}} */

}

?>
