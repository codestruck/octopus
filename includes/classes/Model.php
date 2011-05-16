<?php

Octopus::loadClass('Octopus_DB_Insert');
Octopus::loadClass('Octopus_DB_Update');
Octopus::loadClass('Octopus_DB_Select');
Octopus::loadClass('Octopus_DB_Delete');

Octopus::loadClass('Octopus_Model_Field');
Octopus::loadClass('Octopus_Model_ResultSet');

class Octopus_Model_Exception extends Octopus_Exception {}

abstract class Octopus_Model {//implements ArrayAccess {

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

    public function __construct($id = null) {

        if (is_array($id)) {
            // We're receiving a row of data
            $this->setData($id);
        } else if ($id) {
            $item = self::get($id);
            if ($item) {
                $this->setData($item);
            }
        }
    }

    public function __get($var) {

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
            if ($var === $pk || preg_match('/_id$/', $var)) {
                return null;
            } else {
                throw new Octopus_Model_Exception('Cannot access field ' . $var . ' on Model ' . self::_getClassName());
            }
        }
    }

    public function __set($var, $value) {

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
                throw new Octopus_Model_Exception('Cannot set field ' . $var . ' on Model ' . self::_getClassName());
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


        throw new Octopus_Model_Exception('Cannot call ' . $name . ' on Model ' . $this->_getClassName());

    }

    public function setInternalValue($field, $value) {
        $this->data[$field] = $value;
    }

    public function getInternalValue($field, $default = '') {
        return isset($this->data[$field]) ? $this->data[$field] : $default;
    }

    protected function setData($data) {

        // TODO WHY THE FUCK DOES THIS WORK

        foreach ($data as $key => $value) {
            $this->$key = $value;
        }

    }

    public function save() {

        if (!$this->validate()) {
            //errors?
            return false;
        }

        $pk = $this->getPrimaryKey();

        if ($this->$pk !== null) {
            $i = new Octopus_DB_Update();
            $i->where($pk . ' = ?', $this->$pk);
        } else {
            $i = new Octopus_DB_Insert();
        }

        $i->table($this->getTableName());

        foreach ($this->getFields() as $field) {
            $field->save($this, $i);
        }

        $i->execute();
        if ($this->$pk === null) {
            $this->$pk = $i->getId();
        }

        foreach($this->getFields() as $field) {
            $field->afterSave($this);
        }

        return true;
    }

    public function delete() {

        $pk = $this->getPrimaryKey();
        $item_id = $this->$pk;
        $table = $this->getTableName();

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

    public function validate() {

        $pass = true;
        $this->errors = array();

        foreach ($this->getFields() as $obj) {
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
            return underscore($this->to_id(self::_getClassName()));
        }
    }

    public function to_id($name) {
        return $name . '_id';
    }

    public function getTableName() {
        if (isset($this->table)) {
            return $this->table;
        } else {
            return underscore($this->_pluralize(self::_getClassName()));
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

    protected function _pluralize($str) {
        // needs work...
        return pluralize($str);
    }

    public function getFields() {

        $class = self::_getClassName();

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
        $class = get_called_class();

        $result = new Octopus_Model_ResultSet($class, $criteria);
        return $result;
    }

    /**
     * @param $idOrName mixed Either a record id or the value of the display field (for LIKE comparison)
     * @param $orderBy mixed Order stuff
     * @return Mixed The first matching record found, or false if nothing is found.
     */
    public static function &get($idOrName, $orderBy = null) {

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

    // ArrayAccess Implementation {{{

    /*
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
        $this->$offset = null;
    }
    */

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
        file_put_contents(
            'backtrace',
            Octopus_Debug::dumpToString(debug_backtrace())
        );
        exit();
        $this->_iteratorIndex = -1;
        $this->_iteratorFieldName = null;
    }

    public function valid() {
        dump_r('valid');
        return ($this->_iteratorIndex == 0) || $this->_iteratorFieldName;
    }
    */

    // }}}

}

?>
