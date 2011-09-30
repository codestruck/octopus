<?php

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

    protected function doValidation($input, $field, $data) {
        $otherValue = isset($data[$this->fieldName]) ? $data[$this->fieldName] : '';
        return $input == $otherValue;
    }

}

?>
