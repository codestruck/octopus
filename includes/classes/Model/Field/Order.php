<?php

class Octopus_Model_Field_Order extends Octopus_Model_Field {

    public function __construct($field, $modelClass, $options) {
        parent::__construct($field, $modelClass, $options, 'newInt');
        $this->defaultOptions['form'] = 'true';
    }

}

