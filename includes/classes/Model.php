<?php

class Octopus_Model_Exception extends Octopus_Exception {}

abstract class Octopus_Model implements ArrayAccess, Iterator, Countable, Dumpable {

    /**
     * Name of column that stores the primary key. If not set in a subclass,
     * it is inferred when you call getPrimaryKey().
     */
    protected $primaryKey = null;

    /**
     * Name of table this model uses. If not set in a subclass, it is inferred
     * when you call getTableName()
     */
    protected $table = null;

    /**
     * Name of the field to use when displaying this model e.g. in a list.
     * If an array, the first one that actually exists on the model will be
     * used. Once the correct field name is selected, it is cached.
     */
    protected $displayField = array('name', 'title', 'text', 'summary', 'description');

    private static $fieldHandles = array();

    protected $data = array();

    protected $_id = null;
    private $_exists = null;
    private $dataLoaded = false;

    private $touchedFields = array();

    public $escaped = false;

    public function __construct($id = null) {

        if (is_array($id)) {
            // We're receiving a row of data
            $this->internalSetData($id);
        } else if (is_numeric($id)) {
            $this->_id = $id;
        } else if ($id) {
            $item = self::get($id);
            if ($item) {
                $this->internalSetData($item);
            }
        }
    }

    public function __get($var) {

        if ($var === 'id' || $var === $this->getPrimaryKey())  {

            if ($this->_id && !$this->recordStillExists()) {
                $this->_id = null;
            }

            return $this->_id;
        }

        $field = $this->getField($var);

        if ($field) {

            return $field->accessValue($this);

        } else if (preg_match('/_id$/', $var)) {

            $field = $this->getField(preg_replace('/_id$/', '', $var));

            if ($field) {
                $item = $field->accessValue($this);
                return ($item) ? $item->id : null;
            }

        }

        throw new Octopus_Model_Exception('Cannot access field ' . $var . ' on Model ' . $this->getClassName());
    }

    public function __set($var, $value) {

        if ($this->escaped && !empty($this->touchedFields[$var]) && $this->dataLoaded) {
            throw new Octopus_Model_Exception('Cannot set field ' . $var . ' on Model ' . $this->getClassName() . ' when it is escaped.');
        }

        if ($var === 'id' || $var === $this->getPrimaryKey()) {
            $this->_id = $value;
            return;
        }

        $field = $this->getField($var);

        if (!$field && ends_with($var, '_id', false, $fieldName)) {
            $field = $this->getField($fieldName);
        }

        if ($field) {
            $field->setValue($this, $value);
            return;
        }

        throw new Octopus_Model_Exception('Cannot set field ' . $var . ' on Model ' . $this->getClassName());
    }

    public function __call($name, $arguments) {

        // Handle e.g., addCategory, removeCategory, removeAllCategories,
        // hasCategory

        if (!preg_match('/^(add|remove(All)?|has)([A-Z].*?)$/', $name, $matches)) {
            throw new Octopus_Model_Exception('Cannot call ' . $name . ' on Model ' . $this->getClassName());
        }

        $action = $matches[1];
        $type = $matches[3];

        $fieldName = underscore(pluralize($type));
        $field = $this->getField($fieldName);
        if (!$field) $field = $this->getField(camel_case($fieldName));

        if (!$field) {
            $class = $this->getClassName();
            throw new Octopus_Model_Exception("Cannot call $name on model $class (field $fieldName does not exist).");
        }

        if ($action === 'has') {
            return $field->checkHas(array_shift($arguments), $this);
        } else {
            return $field->handleRelation($action, $arguments, $this);
        }

    }

    /**
     * Compares this instance against something else for equality.
     *
     *    @return
     *    True if:
     *    	* $other is numeric, nonzero, and == to this instance's id,
     *    	* $other is an instance of the same class with a nonzero id equal
     *    	  to this instance's id.
     */
    public function eq($other) {

        if ($other === $this) {
        	return true;
        }

        // Note that without an id, only reference equality works

        if (!$other || !$this->id) {
        	return false;
        }

        if (is_numeric($other)) {
        	return $this->id == $other;
        }

        if (is_object($other)) {

        	$class = get_class($this);
        	$otherClass = get_class($other);

        	return $class === $otherClass && $other->id == $this->id;
        }

        return false;
    }

    public function exists() {
        return ($this->_id !== null);
    }

    /**
     * If the data for this model has not been loaded from the DB, loads it.
	 */
    protected function loadData() {

        if ($this->dataLoaded) {
            return;
        }

        $s = new Octopus_DB_Select();
        $s->comment('Octopus_Model::loadData');
        $s->table($this->getTableName());
        $s->where($this->getPrimaryKey() . ' = ?', $this->_id);
        $row = $s->fetchRow();

        if ($row) {
            $this->internalSetData($row, true, false);
        }

    }

    /**
     * @return True if we've checked that a record with this record's ID exists
     * in the database, false otherwise. Note that the result of this method
     * is cached.
     */
    protected function recordStillExists() {

        if ($this->_exists === null) {

            $s = new Octopus_DB_Select();
            $s->comment('Octopus_Model::recordStillExists');
            $s->table($this->getTableName(), array($this->getPrimaryKey()));
            $s->where($this->getPrimaryKey() . ' = ?', $this->_id);
            $this->_exists = !!$s->getOne();
        }

        return $this->_exists;
    }

    public function getData() { return $this->data; }

    public function getTouchedFields() {
        return $this->touchedFields;
    }

    public function setInternalValue($field, $value, $makesDirty = true) {
        if ($makesDirty) $this->touchedFields[$field] = true;
        $this->data[$field] = $value;
    }

    public function getInternalValue($field, $default = '', $lazyLoad = true) {
        if ($this->_id !== null && empty($this->touchedFields[$field]) && $lazyLoad) {
            $this->loadData();
        }

        return isset($this->data[$field]) ? $this->data[$field] : $default;
    }

    /**
     * @return Boolean Whether the contents fo the given field have been changed
     * since this model was loaded or last saved.
     */
    public function isFieldDirty($fieldName) {

    	if ($fieldName instanceof Octopus_Model_Field) {
    		$fieldName = $fieldName->getFieldName();
    	}

        return !empty($this->touchedFields[$fieldName]);
    }


    public function setData($data) {

    	$this->internalSetData($data, false);

    }

    protected function internalSetData($data, $setPrimaryKey = true, $overwrite = true) {

    	if ($setPrimaryKey) {

			$pk = $this->getPrimaryKey();

	        if (isset($data[$pk])) {
	            $this->id = $data[$pk];
	        }
	    }

        foreach($this->getFields() as $field) {

        	if (!$overwrite && $this->isFieldDirty($field)) {
        		continue;
        	}

            $field->loadValue($this, $data);
        }

        $this->dataLoaded = true;

    }

    public function save() {

        if ($this->escaped) {
            return false;
        }

        // shortcut saving on existing items with no modifications
        if ($this->exists() && empty($this->touchedFields)) {
            return true;
        }

        if ($this->_id === null) {
            $fields = $this->getFields();
        } else {
            $fields = array_keys($this->touchedFields);
        }

        return $this->internalSave($this->_id, $fields);

    }

    /**
     * Saves the contents of the given fields.
     * @param $id Mixed ID of record being saved, if known.
     * @param $fields Array Fields to save.
     * @return Number ID of saved record.
     */
    protected function internalSave($id, &$fields) {

        $pk = $this->getPrimaryKey();

        if ($id !== null) {
            $i = new Octopus_DB_Update();
            $i->where($pk . ' = ?', $id);
        } else {
            $i = new Octopus_DB_Insert();
        }

        $i->table($this->getTableName());

        $workingFields = array();

        foreach ($fields as $field) {
            $field = $workingFields[] = (is_string($field) ? $this->getField($field) : $field);
            $field->save($this, $i);
        }

        // Don't run UPDATEs if there's nothing to update.
        if ($id === null || !empty($i->values)) {
            $i->execute();
        }

        if ($id === null) {
            $this->$pk = $i->getId();
        }

        $this->resetDirtyState();

        foreach($workingFields as $field) {
            $field->afterSave($this);
        }

        return true;
    }

    public function delete() {

        if ($this->_id !== null) {

            $d = new Octopus_DB_Delete();
            $d->table($this->getTableName());
            $d->where($this->getPrimaryKey() . ' = ?', $this->_id);
            $d->execute();

            $this->_id = null;
            $this->_exists = null;

            return true;
        }

        return false;
    }

    public function toArray() {

        // TODO eliminate this an have model implement ArrayAccess

        $ar = array();

        $pk = $this->getPrimaryKey();
        $ar[$pk] = $this->$pk;

        foreach($this->getFields() as $name => $field) {
            $ar[$name] = $this->$name;
        }

        return $ar;
    }

    // THIS IS A DIRTY HACK AND SHOULD BE KILLED
    public function hasProperty($p) {
        return isset($this->data[$p]);
    }

    public function getDisplayField() {

        if (is_string($this->displayField)) {
            return $this->getField($this->displayField);
        } else if (is_array($this->displayField)) {

            $fields = $this->getFields();
            $candidates = $this->displayField;

            foreach($candidates as $f) {
                if (isset($fields[$f])) {
                    $this->displayField = $f;
                    return $fields[$f];
                }
            }

            // check text fields
            foreach ($fields as $f) {
                if ($f instanceof Octopus_Model_Field_String) {
                    $this->displayField = $f->getFieldName();
                    return $f;
                }
            }

        }

    }

    public function getDisplayValue() {
        $field = $this->getDisplayField();

        if (!$field) {
            if ($this->exists()) {
                return $this->id;
            } else {
                return '';
            }
        }

        return $this->{$field->getFieldName()};
    }

    public function getPrimaryKey() {
        if (isset($this->primaryKey)) {
            return $this->primaryKey;
        } else {
            return to_id($this->getClassName());
        }
    }


    public function getTableName() {
        if (isset($this->table)) {
            return $this->table;
        } else {
            return to_table_name($this->getClassName());
        }
    }

    /**
     * Resets the dirty tracking for this model (marks all fields as
     * unchanged).
     */
    protected function resetDirtyState() {
        $this->touchedFields = array();
    }

    /**
     * @return String The actual name of the current class. Caches the
     * result.
     */
    private static function _getClassName() {
        //TODO Cache this
        $class = get_called_class();
        return $class;
    }

    private function getClassName() {
        return get_class($this);
    }

    public function getFields() {

        $class = $this->getClassName();

        if (isset(self::$fieldHandles[$class])) {
            return self::$fieldHandles[$class];
        }

        self::$fieldHandles[$class] = array();

        foreach ($this->fields as $name => $options) {

            if (is_numeric($name)) {
                $name = is_array($options) ? $options['name'] : $options;
                $options = array();
            } else if (is_string($options)) {
                $options = array('type' => $options);
            }

            $field = Octopus_Model_Field::getField($name, $class, $options);
            $fieldName = $field->getFieldName();
            self::$fieldHandles[$class][$fieldName] = $field;
        }

        return self::$fieldHandles[$class];
    }

    public function getField($name) {
        $fields = $this->getFields();
        return isset($fields[$name]) ? $fields[$name] : null;
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

    public function getIndexes() {
        return isset($this->indexes) ? $this->indexes : array();
    }

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
        $class = self::_getClassName();

        return self::internalFind($class, $criteria);
    }

    private static function internalFind($className, $criteria) {

        if (count($criteria) === 1 && isset($criteria[0]) && is_string($criteria[0])) {
            // treat as a free text search
            $result = new Octopus_Model_ResultSet($className);
            return $result->matching($criteria[0]);
        }

        return new Octopus_Model_ResultSet($className, $criteria);
    }

    /**
     * HACK To support magic stuff in PHP 5.2
     */
    public function _find(/* Variable */) {

        $criteria = func_get_args();
        $class = $this->getClassName();

        return self::internalFind($class, $criteria);
    }

    /**
     * HACK To support magic in PHP 5.2
     */
    public function _get($idOrName, $orderBy = null) {
        $class = $this->getClassName();
        return self::internalGet($class, $idOrName, $orderBy);
    }

    /**
     * @param $idOrName mixed Either a record id or the value of the display field (for LIKE comparison)
     * @param $orderBy mixed Order stuff
     * @return Mixed The first matching record found, or false if nothing is found.
     */
    public static function get($idOrName, $orderBy = null) {
        $class = self::_getClassName();
        return self::internalGet($class, $idOrName, $orderBy);
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

    /**
     * @return An empty resultset.
     */
    public static function none() {

        $class = self::_getClassName();
        $result = new Octopus_Model_ResultSet($class, null, null, true);
        return $result;
    }

    public function escape() {
        $this->escaped = true;
    }

    public function __toString() {
        return $this->getDisplayValue();
    }

    public function __dumpHtml() {

        $html = '<table class="sgModelDump" border="0" cellpadding="0" cellspacing="0">';

        $str = h($this);
        $class = h(get_class($this));

        $html .= <<<END
<thead>
    <tr>
        <th colspan="2">{$str} ($class)</th>
    </tr>
</thead>
<tbody>
END;

        $index = 0;
        foreach($this->toArray() as $key => $value) {
            $class = ($index % 2 ? 'even' : 'odd');
            $index++;

            $key = h($key);
            $value = Octopus_Debug::dumpToString($value, true);

            $html .= <<<END
<tr class="$class">
    <td class="octopusDebugModelField">$key</td>
    <td class="octopusDebugModelValue">$value</td>
</tr>
END;
        }

        $html .= '</tbody></table>';

        return $html;
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

    // ArrayAccess Implementation {{{

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

    // }}}

    public function __isset($key) {
        $fields = $this->getFields();
        return array_key_exists($key, $fields);
    }

    public function __unset($key) {
        $this->$key = '';
    }

    // Iterator Implementation {{{

    private $_iteratorIndex = -1;
    private $_iteratorFieldName = null;

    public function current() {
        if ($this->_iteratorIndex == 0) {
            return $this->id;
        } else {
            return $this->{$this->_iteratorFieldName};
        }
    }

    public function key() {
        if ($this->_iteratorIndex == 0) {
            return $this->getPrimaryKey();
        } else {
            return $this->_iteratorFieldName;
        }
    }

    public function next() {
        $this->_iteratorIndex++;

        if ($this->_iteratorIndex > 0) {
            $this->_iteratorFieldName = $this->getFieldNameByIndex($this->_iteratorIndex - 1);
        }

    }

    public function rewind() {
        $this->_iteratorIndex = 0;
        $this->_iteratorFieldName = null;
    }

    public function valid() {
        $fields = $this->getFields();
        return ($this->_iteratorIndex > -1 && $this->_iteratorIndex <= count($fields));
    }

    public function count() {
        $fields = $this->getFields();
        return count($fields) + 1;
    }

    // }}}

}

?>
