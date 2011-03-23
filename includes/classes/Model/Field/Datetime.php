<?php

class SG_Model_Field_Datetime extends SG_Model_Field {

    private $format = 'Y-m-d H:i:s';
    public function __construct($field, $options) {
        parent::__construct($field, $options);

        if ($field == 'created') {
            $this->defaultOptions = array(
                'onCreate' => '_setNow',
            );
        } else if ($field == 'updated') {
            $this->defaultOptions = array(
                'onSave' => '_setNow',
            );
        }
    }

    function _setNow($model, $field) {
        return date($this->format, time());
    }
}

?>
