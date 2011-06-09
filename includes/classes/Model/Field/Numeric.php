<?php

class Octopus_Model_Field_Numeric extends Octopus_Model_Field {

    public function save($model, $sqlQuery) {
        $value = $this->accessValue($model, true);
        $value = preg_replace('/[^\d\.]/', '', $value);
        $sqlQuery->set($this->getFieldName(), $value);
    }

}

?>
