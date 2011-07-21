<?php

Octopus::loadClass('Octopus_Model_Field_String');

class Octopus_Model_Field_Url extends Octopus_Model_Field_String {

    public function __construct($field, $modelClass, $options) {

        $options['onSave'] = array('Octopus_Model_Field_Url', 'onSave');

        parent::__construct($field, $modelClass, $options);
    }

    public static function onSave($model, $field) {

        $value = trim($model->getInternalValue($field->getFieldName()));
        if (!$value) return $value;

        $value = normalize_url($value);

        return $value;
    }


}

?>
