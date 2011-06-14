<?php

Octopus::loadClass('Octopus_Html_Form_Rule');

class Octopus_Html_Form_Rule_Callback extends Octopus_Html_Form_Rule {

    public function __construct($callback, $message = null) {
        parent::__construct($message);
        $this->callback = $callback;
    }

    public function validate($form, $data) {
        return call_user_func($this->callback, $data, $form);
    }

}

?>
