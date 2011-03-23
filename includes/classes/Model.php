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
    public static $primaryKey = null;

    /**
     * Name of table this model uses. If not set in a subclass, it is inferred
     * when you call getTableName()
     */
    public static $table = null;

    /**
     * Name of the field to use when displaying this model e.g. in a list.
     * If an array, the first one that actually exists on the model will be
     * used. Once the correct field is selected, it is cached.
     */
    public static $displayField = array('name', 'title', 'text', 'summary', 'description');

    private static $_cache = array();

    // Map of magic method name patterns to handler funcs
    private static $_magicMethods = array(

        '/^getBy(?P<field>[A-Z][a-zA-Z0-9_]*)$/' => '_getBy',

        '/^findBy(?P<field>[A-Z][a-zA-Z0-9_]*)$/' => '_findBy'

    );

    protected $data = array();

    public function __construct($id = null) {

        if (is_array($id)) {
            // We're receiving a row of data
            $this->setData($id);
        } else if ($id) {
            if ($id) {
                $this->setData(static::get($id));
            }
        }
    }

    public function __get($var) {

        if ($var == 'id') {
            // special case for id
            $pk = static::getPrimaryKey();
            return $pk == 'id' ? null : $this->$pk;
        }

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

        $i->table(static::getTableName());

        foreach (static::getFields() as $name => $field) {
            $field->save($this, $i);
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
        $table = static::getTableName();

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

    public static function getDisplayField() {

        if (!static::$displayField) {
            return;
        }

        if (is_string(static::$displayField)) {
            return static::getField(static::$displayField);
        } else if (is_array(static::$displayField)) {

            $fields = static::getFields();
            $candidates = static::$displayField;

            foreach($candidates as $f) {
                if (isset($fields[$f])) {
                    static::$displayField = $f;
                    return $fields[$f];
                }
            }
        }

        static::$displayField = null;
    }

    public function getDisplayValue() {
        $field = static::getDisplayField();

        if ($field) // HACK to fix tests while working on one to many
        return $this->{$field->getFieldName()};
    }

    public static function getPrimaryKey() {
        if (isset(static::$primaryKey)) {
            return static::$primaryKey;
        } else {
            return underscore(static::to_id(static::_getClassName()));
        }
    }

    public static function to_id($name) {
        return $name . '_id';
    }

    public static function getTableName() {
        if (isset(static::$table)) {
            return static::$table;
        } else {
            return underscore(static::_pluralize(static::_getClassName()));
        }
    }

    /**
     * @return String The actual name of the current class. Caches the
     * result.
     */
    private static function _getClassName() {
        //TODO Cache this
        return get_called_class();
    }

    protected static function _pluralize($str) {
        // needs work...
        return pluralize($str);
    }

    public static function getFields() {

        $className = get_called_class();

        $handles = static::_getStatic('fieldHandles', $className);
        if ($handles) return $handles;

        $handles = array();
        foreach (static::$fields as $name => $options) {

            if (is_numeric($name)) {
                $name = is_array($options) ? $options['name'] : $options;
            }

            $field = SG_Model_Field::getField($name, $options);
            $handles[$name] = $field;
        }

        return static::_setStatic('fieldHandles', $handles, $className);
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

        $criteria = func_get_args();
        $class = static::_getClassName();

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

            $result = static::find(array('id' => $idOrName));
            if ($orderBy) $result = $result->orderBy($orderBy);

            $result = $result->first();

            if ($result) return $result;
        }

        $displayField = static::getDisplayField()->getFieldName();

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
    /*
    public function isSaved() {
        $pk = static::getPrimaryKey();
        return ($this->$pk !== null);
    }
    */

    /**
     * Helper for reading from static storage per-subclass.
     */
    protected static function _getStatic($key, &$className = null) {

        // TODO: Cache class name.
        $className = $className ? $className : get_called_class();

        if (!isset(self::$_cache[$className])) {
            return null;
        }

        if (!isset(self::$_cache[$className][$key])) {
            return null;
        }

        return self::$_cache[$className][$key];
    }

    /**
     * Helper for writing to static storage per-subclass.
     */
    protected static function _setStatic($key, $value, &$className = null) {

        $className = $className ? $className : get_called_class();

        if (!isset(self::$_cache[$className])) {
            self::$_cache[$className] = array();
        }

        self::$_cache[$className][$key] = $value;
        return $value;
    }


}

?>
