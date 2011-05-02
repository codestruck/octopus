<?php

SG::loadClass('SG_Html_Form_Rule');

class SG_Html_Form_Rule_Callback extends SG_Html_Form_Rule {

    public function __construct($callback, $message = null) {
        parent::__construct($message);
        $this->callback = $callback;
    }

    public function validate($form, $data) {
        return call_user_func($this->callback, $form, $data);
    }

}

?>
