<?php

class Octopus_Model_Exception extends Octopus_Exception {}

abstract class Octopus_Model implements ArrayAccess, Iterator, Countable, Dumpable {

    /**
     * Name of column that stores the primary key. If not set in a subclass,
     * it is inferred when you call getPrimaryKey(). This can be set to an
     * array of fields, in which case they will be used as a compound primary
     * key.
     */
    protected $primaryKey = null;

    /**
     * Name of table this model uses. If not set in a subclass, it is inferred
     * when you call getTableName()
     */
    protected $table = null;

    /**
     * Fields on this model.
     * TODO: Examples
     */
    protected $fields = array();

    /**
     * Name of the field to use when displaying this model e.g. in a list.
     * If an array, the first one that actually exists on the model will be
     * used. Once the correct field name is selected, it is cached.
     */
    protected $displayField = array('name', 'title', 'text', 'summary', 'description');

    /**
     * Is data coming off this model HTML-escaped?
     */
    public $escaped = false;

    private static $primaryKeyFields = array();
    private static $displayFields = array();
    private static $tableNames = array();
    private static $fieldHandles = array();

    protected $data = array();

    private $_exists = null;

    private $dataLoaded = false;

    private $touchedFields = array();


    /**
     * Creates a new instance of a Model.
     * @param Mixed $id Either a numeric ID, or an array of data to set this
     * model's values to.
     */
    public function __construct($id = null) {

    	if (!$id) {
    		// This is just a new, id-less model object.
    		return;
    	}

    	if (is_array($id)) {

    		// We've been passed in an array of data to apply to this instance
    		$this->setData($id);

    	} elseif (is_numeric($id)) {

    		// This is a single, most likely numeric, id. This is going to
    		// happen most of the time.
    		$keys = $this->getPrimaryKeyFields();

    		if (count($keys) !== 1) {
    			$class = get_class($this);
    			$count = count($keys);
    			throw new Octopus_Model_Exception("Can't pass a single ID to $class::__construct() because its primary key is made up of $count fields");
    		}

    		foreach($keys as $key) {

    			$key->setValue($this, $id);
    			break;
    		}

    	} else {

    		throw new Octopus_Model_Exception("Bad argument to model constructor: $id");
    	}

    }

    /**
     * Provide property-like access to field values on this model.
     * @param String $var The field to access. If 'id', and this model has a
     * single-column primary key, the value of that field is returned.
     */
    public function __get($var) {

        if ($var === 'id')  {

        	// Support 'id' as an alias for the primary key.
        	return $this->getSinglePrimaryKeyValue();
        }

        $field = $this->getField($var);

        if ($field) {

        	return $this->getFieldValue($field);

        }

        // For has one relations, when asked for the '_id', return the
        // relation's id value, or null if not set. For example,
        // for a has one called 'category', if $var is 'category_id', get
        // the value of the 'category' relation, and return its id

        if (preg_match('/(.+)_id$/', $var, $m)) {

            $field = $this->getField($m[1]);

            if ($field) {

            	$item = $this->getFieldValue($field);
                return ($item) ? $item->id : null;
            }

        }

        throw new Octopus_Model_Exception('Cannot access field ' . $var . ' on Model ' . get_class($this));
    }

    /**
     * Provide property-like write access to fields on this model.
     * @param String $var field to set. If 'id', and this model has a
     * single-field primary key, sets it to $value.
     * @throws Octopus_Model_Exception If this model is currently escaped, or
     * if $var is 'id' and this model has a multi-field primary key.
     */
    public function __set($var, $value) {

        if ($this->escaped && !empty($this->touchedFields[$var]) && $this->dataLoaded) {
            throw new Octopus_Model_Exception('Cannot set field ' . $var . ' on Model ' . get_class($this) . ' when it is escaped.');
        }

        if ($var === 'id') {
            $field = self::checkSinglePrimaryKey($this->getPrimaryKeyFields(), get_class($this), 'set $id');
        } else {
        	$field = $this->getField($var);

	        if (!$field && ends_with($var, '_id', false, $fieldName)) {
	            $field = $this->getField($fieldName);
	        }
	    }

	    if (!$field) {
	    	throw new Octopus_Model_Exception('Cannot set field ' . $var . ' on Model ' . get_class($this));
	    }

        $field->setValue($this, $value);
    }

    /**
     * @return Whether $key corresponds to a field on this model.
     */
    public function __isset($key) {
        $fields = $this->getFields();
        return array_key_exists($key, $fields);
    }

    /**
     * Clears the value of $key on this model.
     */
    public function __unset($key) {

    	$field = $this->getField($key);
    	if ($field) {
    		// TODO: Add clearValue method to Octopus_Model_Field
    		$field->setValue($this, '');
    	}

    }

    /**
     * Supports fields adding methods to model.
     */
    public function __call($name, $arguments) {

        // Handle e.g., addCategory, removeCategory, removeAllCategories,
        // hasCategory

        // TODO: Move this to Field_ManyToMany

        if (!preg_match('/^(add|remove(All)?|has)([A-Z].*?)$/', $name, $matches)) {
            throw new Octopus_Exception_MethodMissing($this, $name, $arguments);
        }

        $action = $matches[1];
        $type = $matches[3];

        $fieldName = underscore(pluralize($type));
        $field = $this->getField($fieldName);
        if (!$field) $field = $this->getField(camel_case($fieldName));

        if (!$field) {
            throw new Octopus_Exception_MethodMissing($this, $name, $arguments, "Field '$fieldName' does not exist");
        }

        if ($action === 'has') {
            return $field->checkHas(array_shift($arguments), $this);
        } else {
            return $field->handleRelation($action, $arguments, $this);
        }

    }

    /**
     * Deletes the DB record for this model.
     * @return Boolean True if something was actually deleted, false otherwise.
     */
    public function delete() {

        $d = new Octopus_DB_Delete();
        $d->table($this->getTableName());

        if (!$this->restrictToThisRecord($d)) {
        	// Missing a primary key value
        	return false;
        }

        $d->execute();

        foreach($this->getFields() as $field) {
        	$field->afterDelete($this);
        }

        $this->_exists = false;

        return !!$d->affectedRows();
    }

    /**
     * Compares this instance against something else for equality.
     *
     *    @return
     *    True if:
     *		  * $other is the same as $this (reference equality)
     *        * $other is numeric, nonzero, and == to this instance's id,
     *        * $other is an instance of the same class with a nonzero id equal
     *          to this instance's id.
     */
    public function eq($other) {

        if ($other === $this) {
            return true;
        }

        if (!$other) {
        	return false;
        }

        if ($other instanceof Octopus_Model) {

        	if (get_class($this) !== get_class($other)) {
        		return false;
        	}

        	$other = $other->getPrimaryKeyValue();
        }

    	$pkFields = $this->getPrimaryKeyFields();
    	$fieldCount = count($pkFields);
    	$otherIsArray = is_array($other);

    	if ($fieldCount === 0) {
    		return false;
    	} else if ($fieldCount === 1) {

    		if ($otherIsArray) {

    			if (count($other) !== 1) {
    				return false;
    			}

    			$other = array_shift($other);
    		}

    		$field = array_shift($pkFields);

    		if (!$this->fieldHasValue($field)) {
    			return false;
    		}

    		return $this->getFieldValue($field) == $other;
    	}

    	// There is more than one field in this model's primary key
    	if (!$otherIsArray || count($other) !== $fieldCount) {
    		return false;
    	}

    	for($i = 0; $i < $fieldCount; $i++) {

    		$field = array_shift($pkFields);

    		if (!$this->fieldHasValue($field)) {
    			return false;
    		}

    		$value = $this->getFieldValue($field);
    		if ($value != array_shift($other)) {
    			return false;
    		}
    	}

    	return true;
    }

    public function escape() {
        $this->escaped = true;
    }

    /**
     * @return Boolean Whether this record still exists in the DB.
     */
    public function exists() {

    	if ($this->_exists !== null) {
    		return $this->_exists;
    	}

        $s = new Octopus_DB_Select();
        $s->comment('Octopus_Model::exists');
        $s->table($this->getTableName());

        if (!$this->restrictToThisRecord($s)) {
        	return ($this->_exists = false);
        }

        // TODO: Would an EXISTS query be more efficient?

		return ($this->_exists = !!$s->fetchRow());
    }

    /**
     * @deprecated Use ::__getRawData()
     */
    public function getData() {
    	// TODO: is this method used anywhere?
    	return $this->__getRawData();
    }

    /**
     * @return Octopus_Model_Field The field used to generate the 'display'
     * value for this model. Like, for ::__toString() and stuff. Defaults to
     * one of 'name', 'title', 'description'. Falls back to the ID.
     * @throws Octopus_Model_Exception If no display field can be found.
     */
    public function getDisplayField() {

    	$class = get_class($this);

    	// NOTE: cache display field once it's been looked up
    	if (isset(self::$displayFields[$class])) {
    		return self::$displayFields[$class];
    	}

    	$candidates = $this->displayField;
    	if (!is_array($candidates)) $candidates = array($candidates);

    	$allFields = $this->getFields();

        foreach($candidates as $fieldName) {

            if (isset($allFields[$fieldName])) {
                return (self::$displayFields[$class] = $allFields[$fieldName]);
            }

        }

        // No candidate fields found, so use the ID.
        $primaryKeyFields = $this->getPrimaryKeyFields();

        if (count($primaryKeyFields) === 1) {
        	return (self::$displayFields[$class] = array_shift($primaryKeyFields));
        }

        throw new Octopus_Model_Exception("Could not determine display field for model $class");

    }

    /**
     * @return Mixed The simple value used for displaying this model. For
     * example, the value of the 'name' field.
     * @see ::getDisplayField
     */
    public function getDisplayValue() {

        $field = $this->getDisplayField();
        $value = $this->getFieldValue($field);

        if ($value === null) {
        	$value = '';
        }

        return $value;
    }

    /**
     * @return Mixed If a field with the given name exists on this model,
     * the corresponding Octopus_Model_Field instance is returned. Otherwise,
     * null is returned.
     */
    public function getField($name) {
        $fields = $this->getFields();
        return isset($fields[$name]) ? $fields[$name] : null;
    }

    /**
     * @return Array The Octopus_Model_Field instances used to represent all
     * the fields this model has (including those that make up the primary
     * key). The resulting array is keyed on field name, with values being
     * the actual Octopus_Model_Field instances.
     */
    public function getFields() {

        $class = get_class($this);

        if (isset(self::$fieldHandles[$class])) {
            return self::$fieldHandles[$class];
        }

        self::$fieldHandles[$class] = array();
        self::$primaryKeyFields[$class] = array();

        // Create fields used to represent primary key

        foreach(self::createPrimaryKeyFields($class, $this->primaryKey) as $pkField) {
        	$name = $pkField->getFieldName();
        	self::$fieldHandles[$class][$name] = $pkField;
        	self::$primaryKeyFields[$class][$name] = $pkField;
        }

        // Create other fields

        foreach(self::createFields($class, $this->fields) as $field) {
        	self::$fieldHandles[$class][$field->getFieldName()] = $field;
        }

        return self::$fieldHandles[$class];
    }

    public function getIndexes() {
    	// TODO: Make this static
        return isset($this->indexes) ? $this->indexes : array();
    }

    /**
     * For internal use. Gets the raw value of $field, optionally lazy
     * loading the record. Used by Octopus_Model_Field to retrieve data
     * stored on this object.
     */
    public function getInternalValue($field, $default = '', $lazyLoad = true) {

    	if ($lazyLoad && !array_key_exists($field, $this->data)) {
    		$this->loadData();
    	}
        return array_key_exists($field, $this->data) ? $this->data[$field] : $default;
    }

    /**
     * For models with a single primary key field, returns the name of that
     * field.
     * @deprecated Use ::getPrimaryKeyFields() to support compound primary
     * keys.
     * @throws Octopus_Model_Exception If this model has a primary key does
     * not have just one column in it.
     */
    public function getPrimaryKey() {

    	$keyFields = $this->getPrimaryKeyFields();

    	if (count($keyFields) !== 1) {
    		$class = get_class($this);
    		$count = count($keyFields);
    		throw new Octopus_Model_Exception("Can't call ::getPrimaryKey() on model $class because the primary key is made up of $count fields.");
    	}

    	$field = array_shift($keyFields);
    	return $field->getFieldName();
    }

    /**
     * @return Array The Octopus_Model_Field instances used to represent this
     * model's primary key.
     */
    public function getPrimaryKeyFields() {

    	$class = get_class($this);

    	if (isset(self::$primaryKeyFields[$class])) {
    		return self::$primaryKeyFields[$class];
    	}

    	// ::getFields() automatically generates the $primaryKeyFields array
    	$this->getFields();

    	return self::$primaryKeyFields[$class];
    }

    /**
     * @return String The table to which this model gets persisted. If ::$table
     * is not set, this is the pluralized form of the model's class name
     * separated_by_underscores.
     * @see to_table_name
     */
    public function getTableName() {

    	$class = get_class($this);

    	if (isset(self::$tableNames[$class])) {
    		return self::$tableNames[$class];
    	}

        if (isset($this->table)) {
            return (self::$tableNames[$class] = $this->table);
        } else {
            return (self::$tableNames[$class] = to_table_name($class));
        }

    }

    /**
     * @return Array Fields whose values have been changed since the last
     * load or save.
     */
    public function getTouchedFields() {
        return $this->touchedFields;
    }

    /**
     * For internal use. Retrieves the raw data array this instance uses to
     * store its values.
     */
    public function __getRawData() {
    	// TODO: is this method used anywhere?
    	return $this->data;
    }

    /**
     * @deprecated Is this required anymore? This was a hack to support
     * model's implementation of isset not working early on.
     */
    public function hasProperty($p) {
        return isset($this->data[$p]);
    }

    /**
     * @return Boolean Whether the contents of the given field have been changed
     * since this model was loaded or last saved.
     */
    public function isFieldDirty($fieldName) {

        if ($fieldName instanceof Octopus_Model_Field) {
            $fieldName = $fieldName->getFieldName();
        }

        return !empty($this->touchedFields[$fieldName]);
    }

    /**
     * Persists this model instance to the database.
     * @return Mixed On save failure, returns false. For models with a single
     * field in their primary key, returns the primary key value. For models
     * with more than one field in their primary key, returns an array of
     * values, keyed on field name.
     * @throws Octopus_Model_Exception You know, if something is super fucked
     * up with the way your fields are defined.
     */
    public function save() {

        if ($this->escaped) {
            return false;
        }

        // shortcut saving on existing items with no modifications
        $exists = $this->exists();

        if (empty($this->touchedFields) && $exists) {
        	return $this->getPrimaryKeyValue();
        }

        $fieldsToSave = $exists ? array_keys($this->touchedFields) : $this->getFields();

        $result = $this->internalSave($fieldsToSave, $exists);

        if ($result) {
        	$this->_exists = true;
        }

        return $result;
    }

    /**
     * Sets zero or more field values on this model in one fell swoop.
     */
    public function setData($data) {
        $this->internalSetData($data, false);
    }

    /**
     * For internal use. Sets the raw value of $field, bypassing any logic.
     * Octopus_Model_Field uses this to actually store data on a model.
     */
    public function setInternalValue($field, $value, $makesDirty = true) {
    	// TODO: refactor out into a helper object and deprecate this method
        if ($makesDirty) $this->touchedFields[$field] = true;
        $this->data[$field] = $value;
    }

    /**
     * @deprecated Octopus_Model implements ArrayAccess. Use that.
     */
    public function toArray() {

    	$ar = array();

        foreach($this->getFields() as $name => $field) {

            $value = $this->$name;

            if ($value instanceof Octopus_Model) {
            	$value = $value->toArray();
            }

            $ar[$name] = $value;
        }

        return $ar;
    }

    /**
     * @deprecated This exists to support PHP 5.2. We need a better way than
     * this.
     */
    public function _find(/* Variable */) {

        $criteria = func_get_args();
        $class = get_class($this);

        return self::internalFind($class, $criteria);
    }

    /**
     * @deprecated This exists to support PHP 5.2 We need a better way than
     * this.
     */
    public function _get($idOrName, $orderBy = null) {
        $class = get_class($this);
        return self::internalGet($class, $idOrName, $orderBy);
    }

    /**
     * @see ::getDisplayValue
     */
    public function __toString() {
        return $this->getDisplayValue();
    }

////////////////////////////////////////////////////////////////////////////////
//
// Static Methods
//
////////////////////////////////////////////////////////////////////////////////

    /**
     * @return Object An Octopus_Model_ResultSet containing all records.
     */
    public static function all() {
        return self::find();
    }

    /**
     * @return Object an Octopus_Model_ResultSet
     */
    public static function find(/* Variable */) {

        $criteria = func_get_args();
        $class = self::getClassName();

        return self::internalFind($class, $criteria);
    }

    /**
     * @param $idOrName mixed Either a record id or the value of the display field (for LIKE comparison)
     * @param $orderBy mixed Order stuff
     * @return Mixed The first matching record found, or false if nothing is found.
     */
    public static function get($idOrName, $orderBy = null) {
        $class = self::getClassName();
        return self::internalGet($class, $idOrName, $orderBy);
    }

    /**
     * Generates the tables this model requires to function.
     * @param Octopus_DB_Schema $schema The schema instance used to create
     * tables.
     * @param Mixed $php52ClassNameHack If you are calling ::migrate from a
     * place that needs to support PHP 5.2, provide the class name being
     * migrated here. This is to compensate for 5.2's lack of get_called_class
     * support.
     */
    public static function migrate(Octopus_DB_Schema $schema, $php52ClassNameHack = null) {

    	// TODO: Make field stuff static on model

    	$modelClass = $php52ClassNameHack ? $php52ClassNameHack : self::getClassName();
    	$model = new $modelClass();

		foreach($model->getFields() as $field) {
            $field->beforeMigrate($schema);
        }

        $table = $schema->newTable($model->getTableName());

        /*
        $table->newKey(to_id($modelClass), true);
        $table->newPrimaryKey(to_id($modelClass));
		*/

        foreach($model->getFields() as $field) {
            $field->migrate($schema, $table);
            $field->migrateIndexes($schema, $table);
        }

        foreach ($model->getIndexes() as $index) {
            if (is_array($index)) {
                $table->newIndex('INDEX', implode('_', $index), $index);
            } else {
                $table->newIndex($index);
            }
        }

        // Create primary key
        $pkFields = $model->getPrimaryKeyFields();
        if (count($pkFields)) {
        	$table->newPrimaryKey(array_keys($pkFields));
        }

        $table->create();

        foreach($model->getFields() as $field) {
            $field->afterMigrate($schema);
        }

    }

    /**
     * @return An empty resultset.
     */
    public static function none() {

        $class = self::getClassName();
        $result = new Octopus_Model_ResultSet($class, null, null, true);
        return $result;
    }


////////////////////////////////////////////////////////////////////////////////
//
// ArrayAccess implementation
//
////////////////////////////////////////////////////////////////////////////////

    public function offsetExists($offset) {
        return ($offset == $this->getPrimaryKey() || $this->getField($offset) !== null);
    }

    public function offsetGet($offset) {
        return $this->$offset;
    }

    public function offsetSet($offset, $value) {
        $this->$offset = $value;
    }

    public function offsetUnset($offset) {
        $this->$offset = '';
    }

////////////////////////////////////////////////////////////////////////////////
//
// Iterator implementation
//
////////////////////////////////////////////////////////////////////////////////

    private $_iteratorFields = null;
    private $_iteratorField = null;

    public function current() {
        return $this->getFieldValue($this->_iteratorField);
    }

    public function key() {
    	return $this->_iteratorField->getFieldName();
    }

    public function next() {
    	$this->_iteratorField = array_shift($this->_iteratorFields);
    }

    public function rewind() {
    	$this->_iteratorFields = $this->getFields();
    	$this->_iteratorField = array_shift($this->_iteratorFields);
    }

    public function valid() {
    	return $this->_iteratorField;
    }

    public function count() {
    	return count($this->getFields());
    }

////////////////////////////////////////////////////////////////////////////////
//
// Dumpable implementation
//
////////////////////////////////////////////////////////////////////////////////

    public function __dumpHtml() {
    	return self::dumpToHtml($this);
    }

    public function __dumpText() {

        $result = get_class($this);
        foreach($this->toArray() as $key => $value) {
            try {

                if ($value instanceof Octopus_Model) {
                    $class = get_class($value);
                    $value = "{$value} ($class, id = {$value->id})";
                } else if ($value instanceof Octopus_Model_ResultSet) {
                    $count = count($value);
                    $params = array();
                    $sql = $value->getSql($params);
                    $sql = normalize_sql($sql, $params);
                    $value = "ResultSet (count = $count, sql = $sql)";
                } else if ($value instanceof Dumpable) {
                  $value = $value->__dumpText();
                }
                $result .= <<<END

\t$key:\t\t$value
END;
            } catch(Exception $ex) {
                $result .= <<<END

\t$key:\t\t<Exception: {$ex->getMessage()}>
END;
            }
        }

        return $result;
    }

////////////////////////////////////////////////////////////////////////////////
//
// Protected methods
//
////////////////////////////////////////////////////////////////////////////////

    protected function internalSetData($data, $setAutoIncrement = true, $overwrite = true) {

        foreach($this->getFields() as $field) {

            if (!$overwrite && $this->isFieldDirty($field)) {
                continue;
            }

            // TODO: modify Field::loadValue so it knows that it's being called
            // from a trusted context, then move this autoincrement check into
            // Field_Numeric
            if ($setAutoIncrement || !$field->getOption('auto_increment', false)) {
            	$field->loadValue($this, $data);
            }
        }

        $this->dataLoaded = true;
    }

    /**
     * If the data for this model has not been loaded from the DB, loads it.
     * @param Boolean $force If true, data is loaded even if this model has
     * already loaded its data.
     * @return Boolean Whether any data was actually loaded into this model.
     * @throws Octopus_Model_Exception If no primary key fields are defined
     * on this model.
     */
    protected function loadData($force = false) {

        if ($this->dataLoaded && !$force) {
            return false;
        }

		$s = new Octopus_DB_Select();
		$s->comment('Octopus_Model::loadData');
        $s->table($this->getTableName());

        if (!$this->restrictToThisRecord($s)) {
        	// Not enough data on model to look up existing record
        	return false;
        }

        $row = $s->fetchRow();

        if ($row) {
            $this->internalSetData($row, true, false);
            return true;
        } else {

        	// The record disappeared out from under us!
        	foreach($this->getFields() as $field) {
        		// for example, Field_Numeric will reset autoincrement values
        		// to null here.  This is potentially a different thing than
        		// an explicit delete
        		$field->recordDisappeared($this);
        	}

        	return false;
        }


    }

    /**
     * Resets the dirty tracking for this model (marks all fields as
     * unchanged).
     */
    protected function resetDirtyState() {
        $this->touchedFields = array();
    }


////////////////////////////////////////////////////////////////////////////////
//
// Private methods
//
////////////////////////////////////////////////////////////////////////////////

    /**
     * @return Boolean Whether any fields that are not part of the primary key
     * have been touched.
     */
    private function anyNonPrimaryKeyFieldsTouched() {

    	if (empty($this->touchedFields)) {
    		return;
    	}

    	foreach($this->getFields() as $field) {

    		if ($this->isPrimaryKeyField($field)) {
    			continue;
    		}

    		if (!empty($this->touchedFields[$field->getFieldName()])) {
    			return true;
    		}

    	}

    	return false;

    }

    private function fieldHasValue(Octopus_Model_Field $field) {

    	if ($field->getOption('auto_increment')) {
    		// TODO: move to a ::hasValue() method on Field?
    		return isset($this->data[$field->getFieldName()]);
    	}

    	return true;
    }

    private function getFieldNameByIndex($index) {
        $fields = $this->getFields();
        foreach($fields as $name => $field) {
            if ($index == 0) {
                return $name;
            }
            $index--;
        }
        return null;
    }

    /**
     * @return Mixed The value of $field on this model.
     */
    private function getFieldValue(Octopus_Model_Field $field) {
        return $field->accessValue($this);
    }

    private function getPrimaryKeyValue() {

    	$fields = $this->getPrimaryKeyFields();
    	$values = array();

    	foreach($fields as $field) {
    		$values[$field->getFieldName()] = $field->accessValue($this);
    	}

    	return (count($values) === 1 ? array_shift($values) : $values);
    }

    /**
     * @return Mixed If this model has a primary key made up of a single
     * column, returns the value of that field, or null if it is not set.
     */
    private function getSinglePrimaryKeyValue() {

    	$field = self::checkSinglePrimaryKey($this->getPrimaryKeyFields(), get_class($this), 'return value of $id');

    	if (!$this->fieldHasValue($field)) {
    		return null;
    	}

    	return $this->getFieldValue($field);
    }

    private function haveEnoughDataForInsert() {

    	$pkFields = $this->getPrimaryKeyFields();

    	$enough = true;

    	foreach($pkFields as $field) {

    		$enough = $enough && (
    			$field->getOption('auto_increment') ||  // this kind of stinks
    			!empty($this->data[$field->getFieldName()])
    		);

    	}

    	return $enough;

    }

    /**
     * Actually saves the contents of the given fields.
     */
    private function internalSave(Array $fields, $exists) {

    	if ($exists) {

    		$i = new Octopus_DB_Update();

    		if (!$this->restrictToThisRecord($i)) {
    			return false;
    		}

    	} else {

    		// Record does not exist, but make sure that we have enough
    		// primary key data to actually insert a row
    		if (!$this->haveEnoughDataForInsert()) {
    			return false;
    		}

    		$i = new Octopus_DB_Insert();
    	}

        $i->table($this->getTableName());

        $workingFields = array();

        foreach ($fields as $field) {

        	if (is_string($field)) {

        		$actualField = $this->getField($field);

        		if (!$actualField) {
        			$class = get_class($this);
        			throw new Octopus_Model_Exception("Can't save field $field on model $class: It is not part of the model.");
        		}

        		$field = $actualField;
        	}

            $workingFields[] = $field;

            $field->save($this, $i);
        }

        // Don't run UPDATEs if there's nothing to update.
        if (!$exists || !empty($i->values)) {
            $i->execute();
        }

        // TODO: move this to Field::afterSave() ? and pass $i?
        $pkFields = $this->getPrimaryKeyFields();
        $singlePkField = null;

    	if (count($pkFields) === 1) {
    		$singlePkField = array_shift($pkFields);

    		if (!$exists && $singlePkField->getOption('auto_increment')) {
    			$singlePkField->setValue($this, $i->getId());
    		}

    	}

        $this->resetDirtyState();

        foreach($workingFields as $field) {
            $field->afterSave($this);
        }

        return $this->getPrimaryKeyValue();
    }

    /**
     * @return Boolean Whether $field is part of this model's primary key.
     */
    private function isPrimaryKeyField(Octopus_Model_Field $field) {
    	$fields = $this->getPrimaryKeyFields();
    	return !empty($fields[$field->getFieldName()]);
    }

    /**
     * Adds WHERE clause data to an Octopus_DB_Select, Octopus_DB_Insert, or
     * Octopus_DB_Delete restricting it to this record.
     * @return True If all restrictions were added, or false if they could
     * not all be added (for example, if one of this model's primary key fields
     * is not set).
     */
    private function restrictToThisRecord($s) {

    	$fields = $this->getPrimaryKeyFields();

    	if (count($fields) === 0) {
    		return false;
    	}

    	foreach($fields as $field) {

    		$name = $field->getFieldName();
    		$value = $this->getInternalValue($name, null, false);

    		if (!$value) {

    			// It is conceivable that a zero value could be a valid primary
    			// key value, but that's an edge case, right?

    			return false;
    		}

    		// Technically, using $field->restrict() would be more "correct"
    		// here, but in practice this is just as good and more efficient
    		// to boot.
    		$s->where("$name = ?", $value);
    	}

    	return true;
    }

    /**
     * @return Mixed If $class has a single field in its primary key, returns
     * a reference to that field. Otherwise, returns null.
     * @throws Octopus_Model_Exception if $class has 0 or more than 1 field in
     * its primary key.
     */
    private static function checkSinglePrimaryKey(Array $keyFields, $class, $action) {

	    $count = count($keyFields);

	    if ($count !== 1) {
    		throw new Octopus_Model_Exception("Can't $action on model $class: Primary key is composed of $count fields.");
    	}

    	// TODO: what is the fastest way to return the first item in an array
    	// when you don't know its key?

    	return array_shift($keyFields);
    }

    /**
     * Takes an array of field definitions, like those present on ::$fields,
     * and generates an array of Octopus_Model_Field objects.
     * @return Array Octopus_Model_Field instances corresponding to
     * $fieldDefs.
     */
    private static function createFields($class, $fieldDefs) {

    	$result = array();

		foreach ($fieldDefs as $name => $options) {

            if (is_numeric($name)) {
                $name = is_array($options) ? $options['name'] : $options;
                $options = array();
            } else if (is_string($options)) {
                $options = array('type' => $options);
            }

            $field = Octopus_Model_Field::getField($name, $class, $options);
            if ($field) $result[] = $field;

        }

        return $result;
    }

    /**
     * Takes whatever this class has in its ::$primaryKey variable and
     * generates an array of field definitions from it.
     * @return Array Octopus_Model_Field instances used to represent a
     * primary key.
     */
    private static function createPrimaryKeyFields($class, $fieldDefs) {

    	if (!$fieldDefs) {

    		// This is the default code path, used when $this->primaryKey is
    		// null. It automatically generates an id field name based on
    		// the class name. So, for a class called Product, this ends
    		// up as 'product_id'

    		$fieldDefs = to_id($class);

    	}

    	if (!is_array($fieldDefs)) {

    		// In this case, $this->primaryKey has been set to a column name.
    		// An auto-incrementing numeric field is used with that name

    		$fieldDefs = array(
    			$fieldDefs => array(
    				'type' => 'numeric',
    				'auto_increment' => true
    			)
    		);

    	}

    	return self::createFields($class, $fieldDefs);
    }

    private static function dumpToHtml(Octopus_Model $instance) {

        $html = '<table class="sgModelDump" border="0" cellpadding="0" cellspacing="0">';

        $str = h($instance);
        $class = h(get_class($instance));

        $html .= <<<END
<thead>
    <tr>
        <th colspan="2">{$str} ($class)</th>
    </tr>
</thead>
<tbody>
END;

		$index = 0;

		foreach($instance->getFields() as $field) {

            $class = ($index % 2 ? 'even' : 'odd');
            $index++;

            $name = h($field->getFieldName());
            $value = Octopus_Debug::dumpToString($instance->getFieldValue($field), true);

            $html .= <<<END
<tr class="$class">
    <td class="octopusDebugModelField">$name</td>
    <td class="octopusDebugModelValue">$value</td>
</tr>
END;
        }

        $html .= '</tbody></table>';

        return $html;


    }

    /**
     * @return String The actual name of the current class. This is a hack
     * to support the lack of a functional get_called_class in PHP 5.2.
     */
    private static function getClassName() {

        // TODO: Come up with another way for 5.2 stuff to figure out what
        // class it is.

        return get_called_class();
    }

    private static function internalFind($className, $criteria) {

        if (count($criteria) === 1 && isset($criteria[0]) && is_string($criteria[0])) {
            // treat as a free text search
            $result = new Octopus_Model_ResultSet($className);
            return $result->matching($criteria[0]);
        }

        return new Octopus_Model_ResultSet($className, $criteria);
    }

    private static function internalGet($className, $idOrName, $orderBy = null) {

        if ($idOrName === null || $idOrName === false) {
            return false;
        }

        if (is_string($idOrName) && trim($idOrName) === '') {
            return false;
        }

        if (is_object($idOrName) && get_class($idOrName) === $className) {
            // Support passing in a model reference (this is useful sometimes)
            return $idOrName;
        }

        if (is_array($idOrName)) {
            $result = self::internalFind($className, $idOrName);
            if ($orderBy) $result = $result->orderBy($orderBy);
            return $result->first();
        }

        if (is_numeric($idOrName)) {

            $result = self::internalFind($className, array('id' => $idOrName));
            if ($orderBy) $result = $result->orderBy($orderBy);

            $result = $result->first();

            return $result;
        }

        // TODO make this stuff static
        $obj = new $className();

        $displayField = $obj->getDisplayField();
        $result = null;

        if ($displayField) {
            $displayField = $displayField->getFieldName();

            $result = self::internalFind($className, array($displayField => $idOrName));
            if ($orderBy) $result = $result->orderBy($orderBy);

            $result = $result->first();
        }

        return $result;
    }


}

?>
