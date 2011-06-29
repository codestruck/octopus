<?php

class Octopus_Model_Field_Boolean extends Octopus_Model_Field {

    public function migrate($schema, $table) {
        $table->newBool($this->getFieldName());
    }

}

?>
