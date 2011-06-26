<?php

Octopus::loadClass('Octopus_DB_Insert');
Octopus::loadClass('Octopus_DB_Update');
Octopus::loadClass('Octopus_DB_Select');
Octopus::loadClass('Octopus_DB_Delete');

Octopus::loadClass('Octopus_Model_Field');
Octopus::loadClass('Octopus_Model_ResultSet');

class Octopus_Model_Exception extends Octopus_Exception {}

abstract class Octopus_Model implements ArrayAccess /*, Countable, Iterator*/ {

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
     * used. Once the correct field is selected, it is cached.
     */
    protected $displayField = array('name', 'title', 'text', 'summary', 'description');

    private static $fieldHandles = array();

    protected $data = array();
    private $errors = array();
    private $load_id = null;
    private $touchedFields = array();
    public $escaped = false;

    public function __construct($id = null) {

        if (is_array($id)) {
            // We're receiving a row of data
            $this->_setData($id);
        } else if (is_numeric($id)) {

            $this->load_id = $id;
            return;

            // when fetching by id, don't call out to find and ResultSet
        } else if ($id) {
            $item = self::get($id);
            if ($item) {
                $this->_setData($item);
            }
        }
    }

    public function __get($var) {

        // TODO: always store primary key in a 'real' var?

        if ($var == 'id') {
            // special case for id
            $pk = $this->getPrimaryKey();
            return $pk == 'id' ? null : $this->$pk;
        }

        $field = $this->getField($var);
        if ($field) {
            return $field->accessValue($this);
        } else {
            $pk = $this->getPrimaryKey();
            $lookForObject = str_replace('_id', '', $var);
            if ($var === $pk) {
                if ($this->load_id !== null) {
                    // verify that this row exists
                    $this->loadData();
                    return isset($this->$pk) ? $this->$pk : null;
                } else {
                    return null;
                }
            } else if (preg_match('/_id$/', $var)) {
                $obj = $this->getField($lookForObject);
                if ($obj) {
                    return $obj->accessValue($this)->id;
                } else {
                    return null;
                }
            } else {
                throw new Octopus_Model_Exception('Cannot access field ' . $var . ' on Model ' . $this->getClassName());
            }
        }
    }

    public function __set($var, $value) {

        if ($this->escaped && $this->load_id === null) {
            throw new Octopus_Model_Exception('Cannot set field ' . $var . ' on Model ' . $this->getClassName() . ' when it is escaped.');
        }

        if ($var == 'id') {
            $pk = $this->getPrimaryKey();
            $this->$pk = $value;
            return;
        }

        $field = $this->getField($var);
        if ($field) {
            $field->setValue($this, $value);
        } else {
            $pk = $this->getPrimaryKey();
            if ($var === $pk || preg_match('/_id$/', $var)) {
                $this->$var = $value;
            } else {
                throw new Octopus_Model_Exception('Cannot set field ' . $var . ' on Model ' . $this->getClassName());
            }
        }
    }

    public function __call($name, $arguments) {
        if (preg_match('/^(add|remove(All)?)(.*?)$/', $name, $matches)) {
            $action = $matches[1];
            $type = $matches[3];
            $fieldname = pluralize(camel_case($type));
            $field = $this->getField($fieldname);
            if ($field) {
                return $field->handleRelation($action, $arguments, $this);
            }
        } else if (preg_match('/^(has)(.*)$/', $name, $matches)) {
            $action = $matches[1];
            $type = $matches[1];
            $fieldname = pluralize(strtolower($matches[2]));

            $field = $this->getField($fieldname);
            if ($field) {
                return $field->checkHas(array_shift($arguments), $this);
            }
        }


        throw new Octopus_Model_Exception('Cannot call ' . $name . ' on Model ' . $this->getClassName());

    }

    /**
     * Writes out some debugging information about this model using dump_r.
     * @param $modelName If provided, only models with this name will be dumped.
     * @return $this To continue the chain.
     */
    public function dump($modelName = '') {

        if ($modelName && $modelName != get_class($this)) {
            return $this;
        }

        $info = array(
            $this->getPrimaryKey() => $this->id
        );

        foreach($this->getFields() as $name => $field) {

            $value = $this->$name;

            if ($value instanceof Octopus_Model_ResultSet) {

                $summary = 'ResultSet (' . $value->getModel();

                try {
                    $count = $value->count();
                    $summary .= ', ' . $count . ' ' . ($count == 1 ? 'item' : 'items');
                } catch (Exception $ex) {
                    $summary .= ', exception during count';
                }

                $value = $summary . ')';
            }

            $info[$name] = $value;
        }

        dump_r($info);

        return $this;
    }

    public function exists() {
        $primaryKey = $this->getPrimaryKey();
        return ($this->load_id !== null || $this->$primaryKey !== null);
    }

    // lazy load from id
    private function loadData() {
        $s = new Octopus_DB_Select();
        $s->table($this->getTableName());
        $s->where($this->getPrimaryKey() . ' = ?', $this->load_id);
        $row = $s->fetchRow();

        if ($row) {
            $this->_setData($row);
        }

        $this->load_id = null;
    }

    public function setInternalValue($field, $value) {
        $this->touchedFields[] = $field;
        $this->data[$field] = $value;
    }

    public function getInternalValue($field, $default = '') {
        if ($this->load_id !== null && !in_array($field, $this->touchedFields)) {
            $this->loadData();
        }

        return isset($this->data[$field]) ? $this->data[$field] : $default;
    }

    protected function _setData($data) {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }

    public function setData($data) {
        $fields = $this->getFields();
        foreach ($data as $key => $value) {
            if (isset($fields[$key])) {
                $this->$key = $value;
            }
        }
    }

    public function save() {

        if ($this->escaped) {
            return false;
        }

        // shortcut saving on existing items with no modifications
        if ($this->exists() && empty($this->touchedFields)) {
            return true;
        }

        if (!$this->validate()) {
            //errors?
            return false;
        }

        if ($this->load_id === null) {
            $fields = $this->getFields();
            return $this->internalSave($this->id, $fields);
        } else {
            return $this->internalSave($this->load_id, $this->touchedFields);
        }

    }

    /**
     * Saves the contents of the given fields.
     * @param $id Mixed ID of record being saved, if known.
     * @param $fields Array Fields to save.
     * @return Number ID of saved record.
     */
    protected function internalSave($id, &$fields) {

        if (empty($fields)) {
            return true;
        }

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

        $this->touchedFields = array();

        foreach($workingFields as $field) {
            $field->afterSave($this);
        }

        return true;
    }

    public function delete() {

        $pk = $this->getPrimaryKey();
        $item_id = $this->$pk;
        $table = $this->getTableName();

        if ($this->load_id !== null) {
            $item_id = $this->load_id;
            $this->load_id = null;
        }

        $d = new Octopus_DB_Delete();
        $d->table($table);
        $d->where($pk . ' = ?', $item_id);
        $d->execute();

        return true; // ?
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

    public function validate() {

        $pass = true;
        $this->errors = array();


        if ($this->load_id !== null) {
            $fields = array_map(array($this, 'getField'), $this->touchedFields);
        } else {
            $fields = $this->getFields();
        }

        foreach ($fields as $obj) {
            if (!$obj->validate($this)) {
                $this->errors[] = array('field' => $obj->getFieldname(), 'message' => 'is Required');
                $pass = false;
            }
        }

        return $pass;

    }

    public function getErrors() {
        return $this->errors;
    }

    public function getDisplayField() {

        if (!$this->displayField) {
            return;
        }

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
        }

        $this->displayField = null;
    }

    public function getDisplayValue() {
        $field = $this->getDisplayField();

        if ($field) // HACK to fix tests while working on one to many
        return $this->{$field->getFieldName()};
    }

    public function getPrimaryKey() {
        if (isset($this->primaryKey)) {
            return $this->primaryKey;
        } else {
            return $this->to_id($this->getClassName());
        }
    }

    public function to_id($name) {
        return underscore($name) . '_id';
    }

    public function getTableName() {
        if (isset($this->table)) {
            return $this->table;
        } else {
            return underscore($this->_pluralize($this->getClassName()));
        }
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

    protected function _pluralize($str) {
        // needs work...
        return pluralize($str);
    }

    public function getFields() {

        $class = $this->getClassName();

        if (isset(self::$fieldHandles[$class])) {
            return self::$fieldHandles[$class];
        }

        foreach ($this->fields as $name => $options) {

            if (is_numeric($name)) {
                $name = is_array($options) ? $options['name'] : $options;
            }

            $field = Octopus_Model_Field::getField($name, $options);
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

    /**
     * @return Object An Octopus_Model_ResultSet containing all records.
     */
    public static function &all() {
        return self::find();
    }

    /**
     * @return Object an Octopus_Model_ResultSet
     */
    public static function &find(/* Variable */) {

        $criteria = func_get_args();
        $class = self::_getClassName();

        if (count($criteria) == 1 && is_string($criteria[0])) {
            // treat as a free text search
            $criteria = self::createFreeTextCriteria($class, $criteria[0]);
        }

        $result = new Octopus_Model_ResultSet($class, $criteria);
        return $result;
    }

    /**
     * @param $idOrName mixed Either a record id or the value of the display field (for LIKE comparison)
     * @param $orderBy mixed Order stuff
     * @return Mixed The first matching record found, or false if nothing is found.
     */
    public static function get($idOrName, $orderBy = null) {

        if ($idOrName === null) {
            return false;
        }

        if (is_object($idOrName) && get_class($idOrName) == self::_getClassName()) {
            // Support passing in a model reference (this is useful sometimes)
            return $idOrName;
        }

        if (is_array($idOrName)) {
            $result = self::find($idOrName);
            if ($orderBy) $result = $result->orderBy($orderBy);
            return $result->first();
        }


        if (is_numeric($idOrName)) {

            $result = self::find(array('id' => $idOrName));
            if ($orderBy) $result = $result->orderBy($orderBy);

            $result = $result->first();

            if ($result) return $result;
        }

        $class = self::_getClassName();
        $obj = new $class();


        $displayField = $obj->getDisplayField();
        $result = null;

        if ($displayField) {

            $displayField = $displayField->getFieldName();

            $result = self::find(array($displayField => $idOrName));
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

    // Iterator Implementation {{{

    /*
    private $_iteratorIndex = -1;
    private $_iteratorFieldName = null;

    public function current() {
        dump_r('current');
        if ($this->_iteratorIndex < 0) {
            return null;
        } else if ($this->_iteratorIndex == 0) {
            return $this->id;
        } else if ($this->_iteratorFieldName) {
            return $this->{$this->_iteratorFieldName};
        }

    }

    public function key() {
        dump_r('key');
        if ($this->_iteratorIndex == 0) {
            return $this->getPrimaryKey();
        } else {
            return $this->_iteratorFieldName;
        }
    }

    public function next() {
        dump_r('next');
        $this->_iteratorIndex++;

        if ($this->_iteratorIndex > 0) {
            $this->_iteratorFieldName = $this->getFieldNameByIndex($this->_iteratorIndex - 1);
        } else {
            $this->_iteratorFieldName = null;
        }

    }

    public function rewind() {

        $this->_iteratorIndex = -1;
        $this->_iteratorFieldName = null;
    }

    public function valid() {
        dump_r('valid');
        return ($this->_iteratorIndex == 0) || $this->_iteratorFieldName;
    }

    public function count() {
        return 7;
    }
    */

    // }}}

}

?>
