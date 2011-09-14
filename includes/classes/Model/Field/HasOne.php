<?php

/**
 * Field representing a "has one" relationship in the database.
 */
class Octopus_Model_Field_HasOne extends Octopus_Model_Field {

    public function accessValue($model, $saving = false) {

        $fieldName = $this->getFieldName();
        $key = to_id($fieldName);
        $class = $this->getItemClass();

        $filtering = $this->getOption('filter', false);
        if ($filtering) {
            $class = ucfirst($model->item_type);
            $key = 'item_id';
        }

        $value = $model->getInternalValue($fieldName);

        if (!$value) {
            
            // Since loadData() on model sets the _id field, check there as well
            $value = $model->getInternalValue($key);
            if (!$value) {
                return null;
            }

        }

        if (is_numeric($value)) {
            
            // We have the ID, need to load up the corresponding object
            $obj = new $class($value);
            $value = array('id' => $value, 'object' => $obj);

            // Cache id / object on the model as an array
            $model->setInternalValue($fieldName, $value, false);
        }

        $obj = isset($value['object']) ? $value['object'] : null;
        if ($obj) {
            $obj->escaped = $model->escaped;
        }

        return $obj;
    }

    public function migrate($schema, $table) {
        $col = to_id($this->getFieldName());
        $table->newKey($col);
        $table->newIndex($col);
    }

    public function save($model, $sqlQuery) {

        $fieldName = $this->getFieldName();
        $key = to_id($fieldName);

        $filtering = $this->getOption('filter', false);
        if ($filtering) {
            $class = ucfirst($model->item_type);
            $key = 'item_id';
        }

        // Since, for example, on a field called 'category', the DB value 
        // will be present on the model as 'category_id', we have to 
        // look both places.
        $value = $model->getInternalValue($fieldName);
        if (!$value) {
            $value = $model->getInternalValue($key);
        }

        $object = null;

        if ($value) {
        
            if (is_array($value)) {
                // setValue() sets $key to array('id' => x, 'object' => y);
                $object = $value['object'];

            } else if (is_numeric($arr)) {
                // Since setValue() sets $key to an array, if it is still numeric,
                // that means it hasn't been touched.
                return;
            }
        }

        if ($object && !$object->validate()) {
            return;
        }

        // MJE: this is to avoid a circular save loop with references
        // Can probably be fixed by implementing dirty detection to avoid saving unneccessarily
        if ($object) {

            if ($this->shouldCascadeSave()) {

                if (!$object->save()) {
                    return;
                }

            } else {

                if (!$object->validate()) {
                    return;
                }

            }

        }

        if ($object && !$object->id) {
            throw new Octopus_Model_Exception("HasOne has an object to save, but it does not have an id.");
        }

        // save id of subobject in this field
        $sqlQuery->set($key, $object ? $object->id : '');
    }

    public function setValue($model, $value) {
        
        $fieldName = $this->getFieldName();
        $key = to_id($fieldName);
        $class = $this->getItemClass();

        $filtering = $this->getOption('filter', false);
        if ($filtering) {
            $class = ucfirst($model->item_type);
            $key = 'item_id';
        }

        // Handle $model->field = null or $model->field = false or whatever
        if (!$value) {
            $model->setInternalValue($fieldName, null);
            $model->setInternalValue($key, null, false);
            return;
        }

        // Handle $model->field = 55
        if (is_numeric($value)) {
            $value = new $class($value);
        }

        if (!is_object($value)) {
            $modelClass = get_class($model);
            throw new Octopus_Model_Exception("Value of HasOne field {$modelClass}.{$fieldName} must be an instance of {$class}, but was '$value'");
        }

        if (!$value instanceof $class) {
            $valueClass = get_class($value);
            throw new Octopus_Model_Exception("Value of HasOne field {$modelClass}.{$fieldName} must be an instance of $class, but was an instance of $valueClass");
        }

        $model->setInternalValue($fieldName, array('id' => $value->id, 'object' => $value));
        $model->setInternalValue($key, $value->id, false);
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

    private function shouldCascadeSave() {
        
        if ($this->getOption('skipsave')) {
            return false;
        }

        return $this->shouldCascade('save', true);
    }

    private function shouldCascadeDelete() {
        return $this->shouldCascade('delete', false);
    }

    private function shouldCascade($operation, $default) {
        
        $cascade = $this->getOption('cascade', $default ? $operation : false);

        if (!$cascade) {
            return false;
        } else if ($cascade === $operation) {
            return true;
        }
        
        if (is_string($cascade)) $cascade = explode(',', $cascade);

        return in_array($operation, $cascade);
    }

    private function getItemClass() {
        // use the 'model' option as the classname, otherwise the fieldname
        $class = $this->getOption('model', $this->getFieldName());
        return ucfirst($class);
    }

}
