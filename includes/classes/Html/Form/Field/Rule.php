<?php

/**
 * A validation rule for a single field.
 */
abstract class Octopus_Html_Form_Field_Rule {

    public $message;

    public function __construct($message = null) {
        $this->message = $message;
    }

    /**
     * @return The error message to use for the given field/data combo.
     */
    public function getMessage($field, $data) {

        if ($this->message === null) {
            return $this->getDefaultMessage($field, $data);
        }

        return $this->message;
    }

    public function setMessage($message) {
        $this->message = $message;
    }

    /**
     * Performs validation.
     * @param $field Object The Octopus_Form_Field being validated.
     * @param $data Array All data posted for the form.
     */
    public function validate($field, $data) {
        $input = $this->getInput($field, $data);
        return $this->doValidation($input, $field, $data);
    }

    protected abstract function doValidation($input, $field, $data);

    /**
     * @return String The input for the given field.
     */
    protected function getInput($field, $data) {

        $name = preg_replace('/\[\]$/', '', $field->name);

        if (!array_key_exists($name, $data)) {
            return '';
        }

        return $data[$name];
    }

    protected function getDefaultMessage($field, $data) {
        return "Validation failed on {$field->name}";
    }

}

?>
