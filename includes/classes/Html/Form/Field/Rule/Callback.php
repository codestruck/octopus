<?php

SG::loadClass('SG_Html_Form_Field_Rule');

class SG_Html_Form_Field_Rule_Callback extends SG_Html_Form_Field_Rule {

    private $callback;

    public function __construct($callback, $message = null) {
        parent::__construct($message);
        $this->callback = $callback;
    }

    public function validate($field, $data) {

        $input = $this->getInput($field, $data);
        if (trim($input) === '') return true;

        return call_user_func($this->callback, $field, $input, $data);
    }

}

?>
