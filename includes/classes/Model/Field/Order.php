<?php

class Octopus_Model_Field_Order extends Octopus_Model_Field {

    public function __construct($field, $modelClass, $options) {
        parent::__construct($field, $modelClass, $options);
        $this->defaultOptions['form'] = 'true';
    }

    public function migrate($schema, $table) {
        $table->newInt($this->getFieldName());
    }

}

?>
