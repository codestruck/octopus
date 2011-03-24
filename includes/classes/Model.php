<?php

SG::loadClass('SG_DB_Insert');
SG::loadClass('SG_DB_Update');
SG::loadClass('SG_DB_Select');
SG::loadClass('SG_DB_Delete');

SG::loadClass('SG_Model_Field');
SG::loadClass('SG_Model_ResultSet');

class SG_Model {

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
    private $fieldHandles = array();

    protected $data = array();

    public function __construct($id = null) {

        if (is_array($id)) {
            // We're receiving a row of data
            $this->setData($id);
        } else if ($id) {
            if ($id) {
                $this->setData(self::get($id));
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
            return null;
        }
    }

    public function __set($var, $value) {
        $field = $this->getField($var);
        if ($field) {
            $field->setValue($this, $value);
        } else {
            $this->$var = $value;
        }
    }

    public function setInternalValue($field, $value) {
        $this->data[$field] = $value;
    }

    public function getInternalValue($field) {
        return isset($this->data[$field]) ? $this->data[$field] : '';
    }

    protected function setData($data) {
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
            $i = new SG_DB_Update();
            $i->where($pk . ' = ?', $this->$pk);
        } else {
            $i = new SG_DB_Insert();
        }

        $i->table($this->getTableName());

        foreach ($this->getFields() as $field) {
            $field->save($this, $i);
        }

        $i->execute();
        if ($this->$pk === null) {
            $this->$pk = $i->getId();
        }

        return true;
    }

    public function delete() {

        $pk = $this->getPrimaryKey();
        $item_id = $this->$pk;
        $table = $this->getTableName();

        $d = new SG_DB_Delete();
        $d->table($table);
        $d->where($pk . ' = ?', $item_id);
        $d->execute();

        return true; // ?
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

        if (count($this->fieldHandles)) {
            return $this->fieldHandles;
        }

        foreach ($this->fields as $name => $options) {

            if (is_numeric($name)) {
                $name = is_array($options) ? $options['name'] : $options;
            }

            $field = SG_Model_Field::getField($name, $options);
            $fieldName = $field->getFieldName();
            $this->fieldHandles[$fieldName] = $field;
        }

        return $this->fieldHandles;
    }

    public function getField($name) {
        $fields = $this->getFields();
        return isset($fields[$name]) ? $fields[$name] : null;
    }

    /**
     * @return Object An SG_Model_ResultSet containing all records.
     */
    public static function &all() {
        return self::find();
    }

    /**
     * @return Object an SG_Model_ResultSet
     */
    public static function &find(/* Variable */) {

        $criteria = func_get_args();
        $class = get_called_class();

        $result = new SG_Model_ResultSet($class, $criteria);
        return $result;
    }

    /**
     * @param $idOrName mixed Either a record id or the value of the display field (for LIKE comparison)
     * @param $orderBy mixed Order stuff
     * @return Mixed The first matching record found, or false if nothing is found.
     */
    public static function &get($idOrName, $orderBy = null) {

        if (is_numeric($idOrName)) {

            $result = self::find(array('id' => $idOrName));
            if ($orderBy) $result = $result->orderBy($orderBy);

            $result = $result->first();

            if ($result) return $result;
        }

        $class = self::_getClassName();
        $obj = new $class();

        $displayField = $obj->getDisplayField()->getFieldName();

        $result = self::find(array($displayField => $idOrName));
        if ($orderBy) $result = $result->orderBy($orderBy);

        return $result->first();
    }

}

?>
