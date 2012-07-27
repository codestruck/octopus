<?php

class Octopus_Settings_Key {

    public $name;
    public $options;

    public function __construct($name, $options = array()) {
        $this->name = $name;
        $this->options = $options;
    }

    /**
     * Applies options for another key to this one.
     */
    public function overlay($name, $options) {

        if (empty($options)) {
            return;
        }

        if (!$this->isWildcard()) {
            $this->options = array_merge($this->options, $options);
            return;
        }

        // For wildcard keys, scope the options to the specific key

        $name = $this->removeWildcardSuffix($name);
        $suffix = trim($this->removeWildcardPrefix($name), '.');
        if ($suffix) $suffix = '.' . $suffix;

        foreach($options as $opt => $value) {
            $this->options[$opt . $suffix] = $value;
        }
    }


    public function createEditor() {

        $field = new Octopus_Html_Form_Field($this->name);

        if (isset($this->options['desc'])) {
            $field->setLabel($this->options['desc']);
        } else if (isset($this->options['label'])) {
            $field->setLabel($this->options['label']);
        } else {
            $field->setLabel(humanize($this->name));
        }

    }

    /**
     * @return Mixed The default value for this setting.
     */
    public function getDefaultValue($name) {

        if ($this->isWildcard()) {
            return $this->getWildcardDefaultValue($name);
        }

        return $this->getDefaultValueRaw();
    }

    private function getDefaultValueRaw($suffix = '', &$found = false) {

        $o =& $this->options;

        if (isset($o['default_value' . $suffix])) {
            $found = true;
            return $o['default_value' . $suffix];
        } else if (isset($o['default' . $suffix])) {
            $found = true;
            return $o['default' . $suffix];
        } else if (isset($o['default_func' . $suffix])) {
            $found = true;
            return call_user_func($o['default_func' . $suffix]);
        }  else {
            $found = false;
            return null;
        }

    }

    private function getWildcardDefaultValue($name) {

        $name = $this->removeWildcardPrefix($name);
        $parts = array_filter(explode('.', $name), 'trim');
        $suffix = '';

        do {

            $suffix = implode('.', $parts);
            if ($suffix) $suffix = '.' . $suffix;

            $value = $this->getDefaultValueRaw($suffix, $found);
            if ($found) return $value;

            array_pop($parts);

        } while(!empty($parts));

        return $this->getDefaultValueRaw();
    }

    private function removeWildcardSuffix($input) {

        if (substr($input, -2) == '.*') {
            $input = substr($input, 0, strlen($input) - 2);
        }

        return $input;
    }

    private function removeWildcardPrefix($input) {

        $prefix = $this->removeWildcardSuffix($this->name);

        $inputLen = strlen($input);
        $prefixLen = strlen($prefix);

        if ($inputLen < $prefixLen) {
            return $input;
        }

        if (substr($input, 0, $prefixLen) == $prefix) {
            $input = substr($input, $prefixLen);
            return ($input === false ? '' : $input);
        }

        return $input;
    }

    /**
     * @param $values Array The array of available settings data.
     * @param $name String Name of key being fetched.
     * @return Mixed The value in the given array. For wildcard keys, returns
     * the longest match
     */
    public function getValue($name, &$values) {

        if ($this->isWildcard()) {
            return $this->getWildcardValue($name, $values);
        }

        if (isset($values[$name])) {
            return $values[$name];
        } else {
            return $this->getDefaultValue($name);
        }

    }

    private function getWildcardValue($name, &$values) {

        $bestValue = $this->getDefaultValue($name);
        $longest = 0;

        foreach($values as $keyName => &$value) {

            $pattern = $this->createPattern($keyName);

            if (preg_match($pattern, $name)) {
                $len = strlen($keyName);
                if ($len > $longest) {
                    $bestValue = $value;
                    $longest = $len;
                }
            }
        }

        return $bestValue;
    }

    /**
     * @return bool Whether this setting has any wildcard components.
     */
    public function isWildcard() {
        return substr($this->name, -2) == '.*';
    }

    /**
     * @return bool Whether this key applies to the given setting name.
     */
    public function matches($name) {
        if ($this->isWildcard()) {
            return preg_match($this->createPattern($this->name), $name);
        } else {
            return strcmp($this->name, $name) == 0;
        }
    }

    /**
     * @return String A regex pattern for matching things under a key name.
     */
    private function createPattern($name) {

        if (substr($name, -2) == '.*') {
            $name = substr($name, 0, strlen($name) - 2);
        }

        return '/' . preg_quote($name) . '(\..+)?/';
    }

    /**
     * Removes all values for the given key (and all keys under it).
     */
    public function remove($name, &$values) {

        $pattern = $name . '.%';
        $d = new Octopus_DB_Delete();
        $d->table('settings');
        $d->where('name =  ? OR name LIKE ?', $name, $pattern);
        $d->execute();

        $pattern = $this->createPattern($name);
        foreach($values as $name => $value) {
            if (preg_match($pattern, $name)) {
                unset($values[$name]);
            }
        }
    }

    /**
     * Clears out this key and replaces its value.
     */
    public function set($name, $value, &$values) {

        $this->remove($name, $values);

        $values[$name] = $value;

        $i = new Octopus_DB_Insert();
        $i->table('settings');
        $i->set('name', $name);
        $i->set('value', $value);
        $i->execute();
    }


}

