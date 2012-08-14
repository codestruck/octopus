<?php

/**
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_Model_Field_Html extends Octopus_Model_Field_String {

    public function __construct($field, $modelClass, $options) {
        parent::__construct($field, $modelClass, $options);
        $this->defaultOptions['form'] = 'true';
    }

    public function migrate(Octopus_DB_Schema $schema, Octopus_DB_Schema_Writer $table, $name = null, $autoIncrement = null) {
    	if (!$name) $name = $this->getFieldName();
        $table->newTextLarge($name);
    }

}

