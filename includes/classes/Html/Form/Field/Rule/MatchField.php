<?php

Octopus::loadClass('Octopus_Html_Form_Field_Rule');

class Octopus_Html_Form_Field_Rule_MatchField extends Octopus_Html_Form_Field_Rule {

  public function __construct($fieldName, $message) {
        parent::__construct($message);
        $this->fieldName = $fieldName;
    }

    public function getDefaultMessage($field, $data) {

        $thisField = str_replace(':', '', $field->label());
        if (!$thisField) $thisField = humanize($field->name);

        $otherField = humanize($this->fieldName);
        $form = $field->getForm();
        if ($form && ($f = $form->getField($this->fieldName))) {
            $label = $f->label();
            if ($label) $otherField = str_replace(':', '', $label);
        }

        return "$thisField does not match $otherField.";
    }

    public function validate($field, $data) {

        $value = isset($data[$field->name]) ? trim($data[$field->name]) : '';
        $otherValue = isset($data[$this->fieldName]) ? trim($data[$this->fieldName]) : '';

        if (!($value || $otherValue)) {
            return true;
        }

        return $value == $otherValue;
    }

}

?>
