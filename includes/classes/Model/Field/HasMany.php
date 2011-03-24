<?php

class SG_Model_Field_HasMany extends SG_Model_Field {

    function save($model, $sqlQuery) {
        // do nothing
    }

    function accessValue($model, $saving = false) {
        $type = strtolower(get_class($model));
        $value = $model->id;
        return new SG_Model_ResultSet($this->field, array($type => $value));

    }

    function getFieldName() {
        return pluralize($this->field);
    }

}
