<?php

class SG_Model_Field_HasOne extends SG_Model_Field {

    function accessValue($model, $saving = false) {
        // if we all ready have an object, return it
        $value = $model->getInternalValue($this->getFieldName());
        if ($value) {
            return $value;
        }

        // setup a model class for this object based on the ID in the DB
        $field = $this->getFieldName();
        $class = ucfirst($field);
        $dataField = $model::to_id($field);

        $value = $model->$dataField; // seems scary to access the join id as a var on the model
        return new $class($value);
    }

    function save($model, $sqlQuery) {
        $field = $this->getFieldName();
        $dataField = $model::to_id($field);

        // save subobject
        $obj = $model->getInternalValue($field);
        $obj->save();

        $primaryKey = $obj->getPrimaryKey();
        $value = $obj->$primaryKey;

        // save id of subobject in this field
        $sqlQuery->set($model::to_id($field), $value);
    }

    function validate($model) {
        $obj = $model->getInternalValue($this->getFieldName());
        return $obj->validate();
    }
}
