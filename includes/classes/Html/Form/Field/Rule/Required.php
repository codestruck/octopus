<?php

/**
 * 'Required' rule.
 */
class Octopus_Html_Form_Field_Rule_Required extends Octopus_Html_Form_Field_Rule {

    public function validate($field, $data) {

        $value = $this->getInput($field, $data);

        if (is_array($value)) {
            return count($value) > 0;
        } else {
            return (trim($value) !== '');
        }
    }

    protected function doValidation($input, $field, $data) {
        return strlen(trim($input)) > 0;
    }

    protected function getDefaultMessage($field, $data) {

        $name = $field->label();
        if ($field->type == 'checkbox') {
            $name = preg_replace('/\[\]$/', '', $field->name);
        }

        $niceName = ucfirst(trim(str_replace(':', '', $name)));

        return "$niceName is required.";
    }
}

