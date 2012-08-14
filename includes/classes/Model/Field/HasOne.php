<?php
/**
 * Field representing a "has one" relationship in the database.
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_Model_Field_HasOne extends Octopus_Model_Field {

    public function accessValue($model, $saving = false) {

        if ($this->getOption('filter', false)) {
            $fieldName = 'item_id';
        } else {
            $fieldName = $this->getFieldName();
        }

        $value = $model->getInternalValue($fieldName);

        if (!$value) {
            return null;
        }

        if (is_numeric($value)) {

            $class = $this->getItemClass($model);

            // We have the ID, need to load up the corresponding object
            $value = new $class($value);

            // Cache object on model
            $model->setInternalValue($fieldName, $value, false);
        }

        $value->escaped = $model->escaped;

        return $value;
    }

    public function loadValue(Octopus_Model $model, $row) {

        $name = $this->getFieldName();
        $col = $this->getColumn();

        if ($this->getOption('filter', false)) {
            return;
        }

        if (isset($row[$col])) {
            $this->setValue($model, $row[$col]);
        } else if (isset($row[$name])) {
            $this->setValue($model, $row[$name]);
        }

    }

    public function setValue($model, $value) {

        $fieldName = $this->getFieldName();

        // Handle $model->field = null or $model->field = false or whatever
        if (!$value) {
            $model->setInternalValue($fieldName, null);
            return;
        }

        $class = $this->getItemClass($model);

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
            $thisClass = get_class($model);
            throw new Octopus_Model_Exception("Value of HasOne field {$thisClass}.{$fieldName} must be an instance of $class, but was an instance of $valueClass");
        }

        $model->setInternalValue($fieldName, $value);
    }

    public function save($model, $sqlQuery) {

        $fieldName = $this->getFieldName();
        $col = $this->getColumn();

        $value = $model->getInternalValue($fieldName);

        if ($value) {

            if (is_numeric($value)) {
                $class = $this->getItemClass($model);
                $value = new $class($value);
            }

        }

        if ($value && $this->shouldCascadeSave()) {

            if (!$value->save()) {
                return false;
            }

        }

        if ($value && !$value->id) {
            throw new Octopus_Model_Exception("HasOne {$this->field} has an object to save, but it does not have an id.");
        }

        $sqlQuery->set($col, $value ? $value->id : '');
    }

    public function migrate(Octopus_DB_Schema $schema, Octopus_DB_Schema_Writer $table, $name = null, $autoIncrement = null) {

    	if (!$name) $name = $this->getColumn();

    	if ($this->getOption('filter')) {

    		// for filtered stuff, id must be numeric, auto increment
    		$pkField = new Octopus_Model_Field_Numeric('item_id', $this->modelClass, array('auto_increment' => true));

    	} else {

	    	$itemClass = $this->getItemClass();
    		$item = new $itemClass();

	    	$pkFields = $item->getPrimaryKeyFields();

	    	if (count($pkFields) !== 1) {
	    		throw new Octopus_Model_Exception("HasOne relationships currently not supported for models with compound primary keys.");
	    	}

	    	$pkField = array_shift($pkFields);
	    }

    	// Migrate the item's primary key field with our custom name.
    	$pkField->migrate($schema, $table, $name, false);
    	$table->newIndex($name);
    }

    public function restrict($expression, $operator, $value, &$s, &$params, $model) {

        // Handle e.g. relation.field
        $expression = is_array($expression) ? $expression : explode('.', $expression);
        $expression = array_filter($expression, 'trim');

        if (!$expression) {
            // Do simple ID comparison
            if (!$value) {

            	// false, null, 0, and '' should all match either zeroes or null values
            	// in the column
            	$id = to_id($this->field);

            	if ($operator === null || $operator === '=' || $operator === '!=' || $operator === 'NOT') {

            		if ($operator === '!=' || $operator === 'NOT') {
            			$conjunction = 'AND';
            		} else {
            			$conjunction = 'OR';
            		}

            		$clauses = array(
            			$this->defaultRestrict($id, $operator, $this->getDefaultSearchOperator(), 0, $s, $params, $model),
            			$this->defaultRestrict($id, $operator, $this->getDefaultSearchOperator(), null, $s, $params, $model),
            			$this->defaultRestrict($id, $operator, $this->getDefaultSearchOperator(), '', $s, $params, $model),
            		);

            		return '(' . implode(") $conjunction (", $clauses) . ')';

            	}

            }

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

    private function getColumn() {

        if ($this->getOption('filter', false)) {
            return 'item_id';
        }

        return to_id($this->getFieldName());
    }

    private function getItemClass($model = null) {

        if ($model && $this->getOption('filter', false)) {
            return ucfirst($model->item_type);
        }

        // use the 'model' option as the classname, otherwise the fieldname
        $class = $this->getOption('model', false);

        if ($class === false) {
            $class = camel_case($this->getFieldName(), true);
        }

        return $class;
    }

    public function restrictFreetext($model, $text) {
        $class = $this->getItemClass($model);

        if (!$class) {
            return null;
        }

        $obj = new $class();
        $displayField = $obj->getDisplayField();
        if (!$displayField) {
            return null;
        }

        $textField = $displayField->getFieldName();

        return new Octopus_Model_Restriction_Field($model, $this->getFieldname() . '.' . $textField . ' LIKE', wildcardify($text));
    }


}
