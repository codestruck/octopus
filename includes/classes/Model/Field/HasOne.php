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
        $class = $this->getItemClass();
        $dataField = $model->to_id($field);

        $value = $model->$dataField; // seems scary to access the join id as a var on the model
        return new $class($value);
    }

    function save($model, $sqlQuery) {
        $field = $this->getFieldName();

        // save subobject
        $obj = $model->getInternalValue($field);

        // we may not have an object?
        if (!$obj) {
            return;
        }

        $obj->save();

        $primaryKey = $obj->getPrimaryKey();
        $value = $obj->$primaryKey;

        // save id of subobject in this field
        $sqlQuery->set($model->to_id($field), $value);
    }

    function setValue($model, $value) {
        if (!is_object($value)) {
            $id = $value;
            $class = $this->getItemClass();

            $value = new $class($id);
        }

        $model->setInternalValue($this->getFieldName(), $value);
    }

    function validate($model) {
        $obj = $this->accessValue($model);
        return $obj->validate();
    }

    public function restrict($operator, $value, &$s, &$params) {
        $model = new SG_Model();
        $sql = $this->defaultRestrict($model->to_id($this->field), $operator, $this->getDefaultSearchOperator(), $value, $s, $params);
        return $sql;
    }

    private function getItemClass() {
        // use the 'model' option as the classname, otherwise the fieldname
        $class = $this->getOption('model', $this->getFieldName());
        return ucfirst($class);
    }
}
