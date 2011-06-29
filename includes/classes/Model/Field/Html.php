<?php

Octopus::loadClass('Octopus_Model_Field_String');

class Octopus_Model_Field_Html extends Octopus_Model_Field_String {

    public function migrate($schema, $table) {
        $table->newTextLarge($this->getFieldName());
    }

}

?>
