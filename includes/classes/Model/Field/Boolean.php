<?php

class Octopus_Model_Field_Boolean extends Octopus_Model_Field {

    public function __construct($field, $modelClass, $options) {
        parent::__construct($field, $modelClass, $options, 'newBool');
        $this->defaultOptions['form'] = 'true';
    }

    public function addToForm(Octopus_Html_Form $form) {

        if (!$this->shouldAddToForm()) {
            return;
        }

        $form->add('checkbox', $this->getFieldName());

    }

}

