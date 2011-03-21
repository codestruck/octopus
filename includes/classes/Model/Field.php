<?php

class SG_Model_Field {

    protected $data;
    private $field;
    private $options;

    public function __construct($field, $options) {
        $this->field = $field;
        $this->options = $options;
    }

    public static function getField($field, $options) {

        if (is_string($options)) {
            $field = $options;
            $options = array();
        }

        $type = 'string';

        if (isset($options['type'])) {
            $type = $options['type'];
        }

        if (($field === 'created' || $field === 'updated') && count($options) == 0) {
            $type = 'datetime';
        }

        $class = 'SG_Model_Field_' . ucfirst($type);
        SG::loadClass($class);

        $obj = new $class($field, $options);
        return $obj;
    }

    function accessValue($model) {
        $value = isset($this->data) ? $this->data : '';
        return $value;
    }

    function saveValue($model) {
        $value = isset($this->data) ? $this->data : '';

        if (!$value) {
            $value = $this->onEmpty($model);
            $this->data = $value;
        }

        return $value;
    }

    function setValue($model, $value) {
        $this->data = $value;
    }

    function getFieldName() {
        return $this->field;
    }

    function getOption($option, $default = null) {
        if (isset($this->options[$option])) {
            return $this->options[$option];
        } else {
            return $default;
        }
    }

    function validate($model) {

        if ($this->getOption('required')) {
            $value = $this->accessValue($model);
            if (!$value) {
                return false;
            }
        }

        return true;
    }


    // handlers
    function onEmpty($model) {

        $fnc = $this->getOption('onEmpty');
        if ($fnc && function_exists($fnc)) {
            return $fnc($model, $this);
        }

        return '';
    }



}

?>
