<?php

/**
 * Class that manages app settings.
 */
class SG_Settings extends SG_Base {

    private $_settings = array();
    private $_values = array();

    /**
     * Adds setting definitions from a file.
     */
    public function addFromFile($file) {

        if (preg_match('/\.yaml/i', $file)) {
            SG::loadExternal('spyc_yaml');
            $data = load_yaml($file);
        } else {
            die("Can't load settings from file: $file");
        }

        $this->_settings = array_merge($this->_settings, $data);
    }

    /**
     * @return Mixed The present value of the given setting.
     */
    public function get($name) {

        if (isset($this->_values[$name])) {
            return $this->_values[$name];
        }

        $setting =& $this->_settings[$name];
        return isset($setting['default']) ? $setting['default'] : null;
    }



}

?>
