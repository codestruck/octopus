<?php

SG::loadClass('SG_Html_Form_Field_Rule');

class SG_Html_Form_Field_Rule_Range extends SG_Html_Form_Field_Rule {

    private $min;
    private $max;

    public function __construct($inclusiveMin, $inclusiveMax, $message = null) {
        parent::__construct($message);

        $this->min = min($inclusiveMin, $inclusiveMax);
        $this->max = max($inclusiveMin, $inclusiveMax);
    }

    public function validate($field, $data) {

        $input = trim($this->getInput($field, $data));

        if ($input === '') {
            return true;
        }

        if (!is_numeric($input)) {
            return false;
        }

        return ($input >= $this->min) && ($input <= $this->max);
    }

    public function getDefaultMessage($field, $data) {
        return "{$field->name} should be between {$this->min} and {$this->max}";
    }

}

?>
