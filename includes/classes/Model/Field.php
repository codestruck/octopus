<?php

class SG_Model_Field {

    private $data;
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

    function getValue($model) {
        $value = isset($this->data) ? $this->data : '';

        if (!$value) {
            $value = $this->onEmpty($model);
        }

        return $value;
    }

    function setValue($model, $value) {
        $this->data = $value;
    }

    function getFieldName() {
        return $this->field;
    }


    // handlers
    function onEmpty($model) {

        if (isset($this->options['onEmpty'])) {
            $fnc = $this->options['onEmpty'];
            if (function_exists($fnc)) {
                return $fnc($model);
            }
        }

        return '';
    }



}

?>
