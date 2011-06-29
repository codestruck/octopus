<?php

class Octopus_Model_Field_HasOne extends Octopus_Model_Field {

    public function accessValue($model, $saving = false) {
        // if we all ready have an object, return it
        $value = $model->getInternalValue($this->getFieldName());
        if ($value) {
            $value->escaped = $model->escaped;
            return $value;
        }

        // setup a model class for this object based on the ID in the DB
        $field = $this->getFieldName();
        $class = $this->getItemClass();
        $dataField = to_id($field);

        $filtering = $this->getOption('filter', false);
        if ($filtering) {
            $class = ucfirst($model->item_type);
            $dataField = 'item_id';
        }

        $value = $model->$dataField;

        $result = new $class($value);
        $result->escaped = $model->escaped;
        return $result;
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
        $sqlQuery->set(to_id($field), $value);
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

    public function restrict($expression, $operator, $value, &$s, &$params, $model) {

        // Handle e.g. relation.field
        $expression = is_array($expression) ? $expression : explode('.', $expression);
        $expression = array_filter($expression, 'trim');

        if (!$expression) {
            // Do simple ID comparison
            return $this->defaultRestrict(to_id($this->field), $operator, $this->getDefaultSearchOperator(), $value, $s, $params, $model);
        }

        // TODO: Make all this stuff static someday
        $itemClass = $this->getItemClass();
        $itemModel = new $itemClass();

        $id = array_shift($expression);
        $field = $itemModel->getField($id);

        if (!$field) {
            throw new Octopus_Model_Exception("Field not found on {$itemClass}: " . $id);
        }

        $sql = $field->restrict($expression, $operator, $value, $s, $params, $itemModel);
        $col = to_id($this->getFieldName());

        return "`{$model->getTableName()}`.`$col` IN (SELECT `{$itemModel->getPrimaryKey()}` FROM `{$itemModel->getTableName()}` WHERE $sql)";
    }

    public function orderBy($expression, $dir, $s, &$params, $model) {

        $expression = is_array($expression) ? $expression : explode('.', $expression);
        $expression = array_filter($expression, 'trim');

        $class = $this->getItemClass();
        $dummyItem = new $class();

        if (!$expression) {

            $displayField = $dummyItem->getDisplayField()->getFieldName();

            // TODO: Support multiple candidate display fields.
            return $this->orderBy($displayField, $dir, $s, $params, $model);
        }

        // FindPost::all()->orderBy('author')
        // ^^ model ^^             ^^ item ^^

        $id = array_shift($expression);
        $field = null;
        $isPrimaryKey = (strcasecmp($id, 'id') == 0) || (strcasecmp($id, $dummyItem->getPrimaryKey()) == 0);

        if (!$isPrimaryKey) {
            $field = $dummyItem->getField($id);
            if (!$field) {
                throw new Octopus_Model_Exception("Field not found on {$this->getItemClass()}: $id");
            }
        }

        $modelTable = $model->getTableName();
        $modelPrimaryKey = $model->getPrimaryKey();

        $itemTable = $dummyItem->getTableName();
        $itemPrimaryKey = $dummyItem->getPrimaryKey();

        $foreignKey = to_id($this->field);

        $s->leftJoin($itemTable, array("`$itemTable`.`$itemPrimaryKey`","`$modelTable`.`$foreignKey`"), array());

        if (empty($expression)) {

            if ($isPrimaryKey) {
                Octopus_Model_Field::defaultOrderBy($dummyItem->getPrimaryKey(), $dir, $s, $params, $dummyItem);
            } else {
                $s->orderBy("`$itemTable`.`{$field->getFieldName()}` $dir");
            }

        } else {
            $field->orderBy($expression, $dir, $s, $params, $dummyItem);
        }
    }

    protected function orderBySubexpression($expression, $dir, $s, &$params, $model) {

    }


    private function getItemClass() {
        // use the 'model' option as the classname, otherwise the fieldname
        $class = $this->getOption('model', $this->getFieldName());
        return ucfirst($class);
    }
}
