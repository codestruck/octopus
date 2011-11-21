<?php

class Octopus_Model_Field_Boolean extends Octopus_Model_Field {

    public function __construct($field, $modelClass, $options) {
        parent::__construct($field, $modelClass, $options);
        $this->defaultOptions['form'] = 'true';
    }

    public function migrate($schema, $table) {
        $table->newBool($this->getFieldName());
    }

    public function addToForm($form) {

    	if (!$this->shouldAddToForm()) {
    		return;
    	}

    	$form->add('checkbox', $this->getFieldName());

    }

}

?>
