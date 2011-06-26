<?php

Octopus::loadClass('Octopus_Html_Form_Field_Rule');

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

    // NOTE: since whether doValidation is called depends on the value of the
    // $emptyIsValid instance variable, required doesn't use it.
    protected function doValidation($input, $field, $data) {
        throw new Octopus_Exception("Not used.");
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

?>
