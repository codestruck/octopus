<?php

/**
 * A validation rule.
 */
abstract class SG_Html_Form_Rule {

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
     * @param $field Object The SG_Form_Field being validated.
     * @param $data Array All data posted for the form.
     */
    public abstract function validate($field, $data);

    /**
     * @return String The input for the given field.
     */
    protected function getInput($field, $data) {

        if (!isset($data[$field->name])) {
            return '';
        }

        return $data[$field->name];

    }

    protected function getDefaultMessage($field, $data) {
        return "Validation failed on {$field->name}";
    }

}

?>
