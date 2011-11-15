<?php

class Octopus_Model_Field_Html extends Octopus_Model_Field_String {

    public function __construct($field, $modelClass, $options) {
        parent::__construct($field, $modelClass, $options);
        $this->defaultOptions['form'] = 'true';
    }

    public function migrate($schema, $table) {
        $table->newTextLarge($this->getFieldName());
    }

}

?>
