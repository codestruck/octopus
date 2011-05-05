<?php

class Octopus_Model_Field_HasMany extends Octopus_Model_Field {

    public function save($model, $sqlQuery) {
        // do nothing
    }

    public function accessValue($model, $saving = false) {
        $type = strtolower(get_class($model));
        $value = $model->id;

        return new Octopus_Model_ResultSet($this->field, array($type => $value));

    }

    public function getFieldName() {
        return pluralize($this->field);
    }

}
