<?php

SG::loadClass('SG_DB_Insert');
SG::loadClass('SG_DB_Update');
SG::loadClass('SG_DB_Select');
SG::loadClass('SG_DB_Delete');

SG::loadClass('SG_Model_Field');
SG::loadClass('SG_Model_ResultSet');

class SG_Model {

    // Map of magic method name patters to handler funcs
    private static $_magicMethods = array(

        '/^getBy(?P<field>[A-Z][a-zA-Z0-9_]*)$/' => '_getBy',

        '/^findBy(?P<field>[A-Z][a-zA-Z0-9_]*)$/' => '_findBy'

    );

    protected static $_className = null;
    protected static $fieldHandles = null;

    public function __construct($id = null) {

        if ($id) {
            $this->setData(static::get($id));
        }
    }

    public function __get($var) {
        $field = static::getField($var);
        if ($field) {
            return $field->accessValue($this);
        } else {
            return null;
        }
    }

    public function __set($var, $value) {
        $field = static::getField($var);
        if ($field) {
            $field->setValue($this, $value);
        } else {
            $this->$var = $value;
        }
    }

    protected function setData($data) {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }

    // find functions only here to support constructor
    public function findOne() {
        $results = call_user_func_array(array($this, 'find'), func_get_args());
        return array_shift($results);
    }


    public function save() {

        if (!$this->validate()) {
            //errors?
            return false;
        }

        $pk = static::getPrimaryKey();

        if ($this->$pk !== null) {
            $i = new SG_DB_Update();
            $i->where($pk . ' = ?', $this->$pk);
        } else {
            $i = new SG_DB_Insert();
        }

        $i->table($this->getTableName());

        foreach ($this->fieldHandles as $obj) {
            $i->set($obj->getFieldName(), $obj->saveValue($this));
        }

        $i->execute();
        if ($this->$pk === null) {
            $this->$pk = $i->getId();
        }

        return true;
    }

    public function delete() {

        $pk = static::getPrimaryKey();
        $item_id = $this->$pk;
        $table = $static::getTableName();

        $d = new SG_DB_Delete();
        $d->table($table);
        $d->where($pk . ' = ?', $item_id);
        $d->execute();

        return true; // ?
    }

    public function validate() {

        $pass = true;
        $this->errors = array();

        foreach (static::getFields() as $obj) {
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

    public function isSaved() {
        $pk = static::getPrimaryKey();
        return ($this->$pk !== null);
    }

    public function getDisplayField() {
        return 'title';
    }

    public function getDisplayValue() {
        $field = static::getDisplayField();
        return $this->$field;
    }

    public static function getPrimaryKey() {
        return static::getItemName() . '_id';
    }

    public static function getItemName() {
        return strtolower(static::_getClassName());
    }

    public static function getTableName() {
        return strtolower(static::_pluralize(static::_getClassName()));
    }

    private static function _getClassName() {
        if (isset(static::$_className)) {
            return static::$_className;
        }
        return static::$_className = get_called_class();
    }

    protected static function _pluralize($str) {
        // needs work...
        return $str . 's';
    }

    public static function &getFields() {

        if (empty(static::$fieldHandles)) {
            foreach (static::$fields as $field => $options) {
                $obj = SG_Model_Field::getField($field, $options);
                $field = $obj->getFieldName();
                static::$fieldHandles[$field] = $obj;
            }
        }

        return static::$fieldHandles;
    }

    public static function getField($name) {
        $fields = static::getFields();
        return isset($fields[$name]) ? $fields[$name] : null;
    }

    /**
     * @return Object An SG_Model_ResultSet containing all records.
     */
    public static function &all() {
        return static::find();
    }

    /**
     * @return Object an SG_Model_ResultSet
     */
    public static function &find(/* Variable */) {

        $args = func_get_args();
        $criteria = call_user_func_array(array('SG_Model_ResultSet', 'makeCriteriaArray'), $args);

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

            $keyField = static::getPrimaryKey();

            $result = static::find(array($keyField => $idOrName));
            if ($orderBy) $result = $result->orderBy($orderBy);

            $result = $result->first();

            if ($result) return $result;

        }

        $displayField = static::getDisplayField();

        $result = static::find(array($displayField => $idOrName));
        if ($orderBy) $result = $result->orderBy($orderBy);

        return $result->first();
    }


    /**
     * Handles findBy*() calls.
     *
     * @param $matches Array Matches from the regex run against the requested function name.
     * @param $args Array Function args.
     * @return Object An SG_Model_ResultSet containing all found things
     *
     * Signature for findBy*() is:
     *
     *  function findBy*($value, $orderBy = null)
     *
     */
     private static function _findBy($matches, $args) {

         $field = $matches['field'];
         $value = $args[0];
         $orderBy = isset($args[1]) ? $args[1] : null;

         $result = static::find(array(underscore($field) => $value));
         if ($orderBy) $result = $result->orderBy($orderBy);
         return $result;

     }

    /**
     * Handles getBy*() calls.
     *
     * @param $matches Array Matches from the regex run against the requested function name.
     * @param $args Array Arguments passed to the function.
     * @return Object An SG_Model instance if one is found, otherwise false.
     *
     * getBy*() has the following signature:
     *
     *  function getBy*($idOrName, $orderBy = null)
     */
    private static function _getBy($matches, $args) {

        $field = $matches['field'];
        $value = $args[0];
        $orderBy = isset($args[1]) ? $args[1] : null;

        $result = static::find(underscore($field), $value);
        if ($orderBy) $result = $result->orderBy($orderBy);

        return $result->first();
    }


    // NB: This requires PHP 5.3.0
    public static function __callStatic($name, $args) {

        foreach(self::$_magicMethods as $pattern => $handler) {

            if (preg_match($pattern, $name, $m)) {
                return static::$handler($m, $args);
            }

        }

    }


}

?>
