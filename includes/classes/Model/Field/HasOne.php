<?php

class Octopus_Model_Field_HasOne extends Octopus_Model_Field {

    public function accessValue($model, $saving = false) {
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

    public function save($model, $sqlQuery) {
        $field = $this->getFieldName();

        // save subobject
        $obj = $model->getInternalValue($field);

        // we may not have an object?
        if (!$obj || !$obj->validate()) {
            return;
        }

        // MJE: this is to avoid a circular save loop with references
        // Can probably be fixed by implementing dirty detection to avoid saving unneccessarily
        if (!$this->getOption('skipsave')) {
            $obj->save();
        }

        $primaryKey = $obj->getPrimaryKey();
        $value = $obj->$primaryKey;

        // save id of subobject in this field
        $sqlQuery->set($model->to_id($field), $value);
    }

    public function setValue($model, $value) {
        if (!is_object($value)) {
            $id = $value;
            $class = $this->getItemClass();

            $value = new $class($id);
        }

        $model->setInternalValue($this->getFieldName(), $value);
    }

    public function validate($model) {
        $obj = $this->accessValue($model);
        if ($this->getOption('required')) {
            return $obj->validate();
        } else {
            return true;
        }
    }

    public function restrict($operator, $value, &$s, &$params, $model) {
       $sql = $this->defaultRestrict($model->to_id($this->field), $operator, $this->getDefaultSearchOperator(), $value, $s, $params, $model);
        return $sql;
    }

    private function getItemClass() {
        // use the 'model' option as the classname, otherwise the fieldname
        $class = $this->getOption('model', $this->getFieldName());
        return ucfirst($class);
    }
}
