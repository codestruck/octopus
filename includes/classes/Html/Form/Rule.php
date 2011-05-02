<?php

/**
 * A validation rule run against an entire form.
 */
abstract class SG_Html_Form_Rule {

    public $message;

    public function __construct($message = null) {
        $this->message = $message;
    }

    /**
     * @return The error message to use for the given form/data combo.
     */
    public function getMessage($form, $data) {

        if ($this->message === null) {
            return $this->getDefaultMessage($form, $data);
        }

        return $this->message;
    }

    public function setMessage($message) {
        $this->message = $message;
    }

    /**
     * Performs validation.
     * @param $form Object The SG_Html_Form being validated.
     * @param $data Array All data posted for the form.
     */
    public abstract function validate($field, $data);

    protected function getDefaultMessage($form, $data) {
        return "Validation failed";
    }
}

?>
