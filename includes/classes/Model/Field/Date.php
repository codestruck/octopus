<?php

class Octopus_Model_Field_Date extends Octopus_Model_Field_Datetime {

    public function __construct($field, $modelClass, $options) {
    	parent::__construct($field, $modelClass, $options);
    	$this->defaultOptions['date_format'] = 'Y-m-d';
    }


    public function migrate($schema, $table) {
        $table->newDate($this->getFieldName());
    }


}
