<?php

Octopus::loadClass('Octopus_Html_Form_Field_Rule');

class Octopus_Html_Form_Field_Rule_Callback extends Octopus_Html_Form_Field_Rule {

    private $callback;

    public function __construct($callback, $message = null) {
        parent::__construct($message);
        $this->callback = $callback;
    }

    public function validate($field, $data) {

        $input = $this->getInput($field, $data);

        return call_user_func($this->callback, $input, $data, $field);
    }

}

?>
