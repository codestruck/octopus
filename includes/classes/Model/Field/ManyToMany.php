<?php

class Octopus_Model_Field_ManyToMany extends Octopus_Model_Field {

    private $joinTableName = null;
    private $joinedModelClass = null;

    public function save($model, $sqlQuery) {

        $values = $model->getInternalValue($this->getFieldName());
        if (is_array($values) && $model->exists()) {
            $this->handleRelation('removeAll', null, $model);
            $this->handleRelation('add', $values, $model);
        }

    }

    /**
     * @return Octopus_Model_ResultSet
     */
    public function accessValue($model, $saving = false) {

        $type = camel_case(singularize($this->field));
        $key = $this->getOption('field', pluralize(strtolower(get_class($model))));
        $value = $model->id;

        $search = array($key => $value);

        $resultSet = new Octopus_Model_ResultSet($this->getJoinedModelClass(), $search);
        $resultSet->escaped = $model->escaped;

        return $resultSet;
    }

    public function migrate(Octopus_DB_Schema $schema, Octopus_DB_Schema_Writer $table, $name = null, $autoIncrement = null) {

        $joinTable = $schema->newTable($this->getJoinTableName());

        $modelKeyFields = $this->getModelKeyFields();
        $joinKeyFields = $this->getJoinKeyFields();

        $joinPrimaryKey = array();

        foreach($modelKeyFields as $field) {
        	$field->migrate($schema, $joinTable, null, false);
        	$joinTable->newIndex($field->getFieldName());
        	$joinPrimaryKey[] = $field->getFieldName();
        }

        foreach($joinKeyFields as $field) {
        	$field->migrate($schema, $joinTable, null, false);
        	$joinTable->newIndex($field->getFieldName());
        	$joinPrimaryKey[] = $field->getFieldName();
        }

        /*
        TODO: individual indexes on columns + 2 col primary key currently craps out
        sort($joinPrimaryKey);
        $joinTable->newPrimaryKey($joinPrimaryKey);
        */

    	$joinTable->create();

    }

    /**
     *
     */
    public function restrict($expression, $operator, $value, &$s, &$params, $model) {

        $joinTable = $this->getJoinTableName();

        $on = array();
        foreach($this->getModelKeyFields() as $field) {
        	$on[] = $field->getFieldName();
        }

        $s->innerJoin($joinTable, $on, array());

        $foreign = array();
        foreach($this->getJoinKeyFields() as $field) {
        	$foreign[] = $field->getFieldName();
        }

        $foreignCount = count($foreign);

        if ($foreignCount === 0) {
        	throw new Octopus_Model_Exception("Not enough join key fields");
        } else if ($foreignCount === 1) {
        	$foreign = array_shift($foreign);
        }

        return $this->defaultRestrict(array($joinTable, $foreign), $operator, $this->getDefaultSearchOperator(), $value, $s, $params, $model);
    }

    public function getFieldName() {
        return pluralize($this->field);
    }

    /**
     * @return String The name of the model class that is joined by this
     * field. For example, if a field 'categories' was defined on the model
     * 'Product', this would return 'Category'.
     */
    public function getJoinedModelClass() {

        if ($this->joinedModelClass) {
            return $this->joinedModelClass;
        }

        $class = $this->getOption('model');
        if (!$class) $class = $this->getOption('class', camel_case(singularize($this->getFieldName()), true));
        return $this->joinedModelClass = $class;

    }

    /**
     * @return Array The primary key fields for the model on the lefthand side
     * of this many-to-many relationship.
     */
    protected function getModelKeyFields() {
    	$modelClass = $this->getModelClass();
    	$m = new $modelClass();
    	return $m->getPrimaryKeyFields();
    }

    /**
     * @return Array The primary key fields for the model on the righthand
     * side of this many-to-many relationship.
     */
    protected function getJoinKeyFields() {
        $class = $this->getJoinedModelClass();
        $m = new $class();
        return $m->getPrimaryKeyFields();
    }

    /**
     * @return String Third table used to join objects in a many-to-many
     * relationship.
     */
    public function getJoinTableName() {

        if ($this->joinTableName) {
            return $this->joinTableName;
        }

        $thisModel = singularize(underscore($this->getModelClass()));
        $joinedModel = singularize(underscore($this->getOption('model', $this->getFieldName())));

        $customTable = $this->getOption('model', false);
        $customField = $this->getOption('field', false);
        $relation = $this->getOption('relation', false);
        if ($customTable && $customField && !$relation) {
            throw Octopus_Exception('you must set relation option on custom manyToMany fields');
        }

        if ($relation) {
            $tables = array(singularize(underscore($joinedModel)), $thisModel);
            sort($tables);
            return $this->joinTableName = sprintf('%s_%s_%s_join', $tables[0], $tables[1], $relation);
        }

        $tables = array($thisModel, $joinedModel);
        sort($tables);

        return $this->joinTableName = sprintf('%s_%s_join', $tables[0], $tables[1]);
    }

    /**
     * Handles an add or remove call for this field.
     * @param $action One of "add", "remove" or "removeAll"
     * @param $obj Object being added or removed
     * @param $model Model to/from which $obj is being added/removed
     */
    public function handleRelation($action, $obj, $model) {

        if ($action === 'removeAll') {
            $this->clearJoinTableEntries($model);
            return;
        }

        // ignore junk values
        if ($obj === null) {
            return;
        }

        // handle array of objects
        if (!is_object($obj) && is_array($obj)) {
            foreach ($obj as $item) {
                $this->handleRelation($action, $item, $model);
            }
            return;
        }

        $joinedClass = $this->getJoinedModelClass();

        if (is_object($obj)) {

            if ($obj instanceof $joinedClass) {
                // Save changes before adding
                $obj->save();
            }
            if (!($obj instanceof $joinedClass)) {
                $name = $this->getFieldName();
                $actualClass = get_class($obj);
                throw new Octopus_Model_Exception("Many-to-many relation $name only supports $joinedClass ($actualClass provided).");
            }

        } else if (is_numeric($obj)) {

            // Handle IDs being passed in
            $obj = new $joinedClass($obj);
        }

        $this->clearJoinTableEntries($model, $obj);

        if ($action === 'add') {

            if (!$model->id) {
                $modelClass = $this->getModelClass();
                $fieldName = $this->getFieldName();
                throw new Octopus_Model_Exception("$modelClass needs to be saved before calling $action on it ($fieldName)");
            }

            if (!$obj->id) {
                $modelClass = $this->getModelClass();
                $fieldName = $this->getFieldName();
                throw new Octopus_Model_Exception("$joinedClass needs to be saved before calling $action ($modelClass.$fieldName)");
            }

            $i = new Octopus_DB_Insert();
            $i->table($this->getJoinTableName());

            foreach($this->getModelKeyFields() as $field) {
            	$name = $field->getFieldName();
            	$value = $field->accessValue($model);
            	$i->set($name, $value);
            }

            foreach($this->getJoinKeyFields() as $field) {
            	$name = $field->getFieldName();
            	$value = $field->accessValue($obj);
            	$i->set($name, $value);
            }

            $i->execute();
        }

    }

    /**
     * @return Boolean Whether $obj is present on $model
     */
    public function checkHas($obj, Octopus_Model $model) {

    	$joinedClass = $this->getJoinedModelClass();

    	if (is_numeric($obj)) {
    		// id
    		$obj = new $joinedClass($obj);
    	} else if (!$obj instanceof $joinedClass) {
            return false;
        }

        $s = new Octopus_DB_Select();
        $s->table($this->getJoinTableName());

        if (!$this->limitToModel($s, $model)) {
        	return false;
        }

        if (!$this->limitToModel($s, $obj)) {
        	return false;
        }

        $query = $s->query();

        return $query->numRows() > 0;
    }

    /**
     * Removes all rows in the join table for the given model instance.
     */
    private function clearJoinTableEntries($model, $other = null) {

        $d = new Octopus_DB_Delete();
        $d->table($this->getJoinTableName());

        if (!$this->limitToModel($d, $model)) {
        	return;
        }

        if ($other && !$this->limitToModel($d, $other)) {
        	return;
        }

        $d->execute();
    }

    /**
     * Limits a query to the join table to a specific model instance.
     */
    private function limitToModel($s, Octopus_Model $model) {

    	$nonFalsey = 0;

    	foreach($model->getPrimaryKeyFields() as $field) {

    		$value = $field->accessValue($model);
    		if ($value) $nonFalsey++;

    		$s->where($field->getFieldName() . ' = ?', $value);

    	}

    	return $nonFalsey > 0;

    }


}

