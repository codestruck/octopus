<?php

SG::loadClass('SG_DB_Insert');
SG::loadClass('SG_DB_Update');
SG::loadClass('SG_DB_Select');
SG::loadClass('SG_DB_Delete');

SG::loadClass('SG_Model_Field');

class SG_Model {

    private $fieldHandles = array();

    public function __construct($id = null) {

        foreach (static::$fields as $field => $options) {
            $obj = SG_Model_Field::getField($field, $options);
            $field = $obj->getFieldName();
            $this->fieldHandles[$field] = $obj;
        }

        if ($id) {
            $item = $this->findOne($this->getPrimaryKey(), $id);
            $this->setData($item);
        }
    }

    public function __get($var) {
        if (isset($this->fieldHandles[$var])) {
            return $this->fieldHandles[$var]->accessValue($this);
        } else {
            return null;
        }
    }

    public function __set($var, $value) {
        if (isset($this->fieldHandles[$var])) {
            $this->fieldHandles[$var]->setValue($this, $value);
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

    public function find() {
        $function_args = func_get_args();
        $num = func_num_args();

        if ($num > 1) {
            // multiple args (hopefully even number)
            $args = array();
            for ($i = 0; $i < $num; $i += 2) {
                $key = $function_args[$i];
                $value = $function_args[$i + 1];
                $args[$key] = $value;
            }

        } else {
            // array of arguments
            $args = $function_args[0];
        }

        $s = new SG_DB_Select();
        $s->table($this->getTableName());

        foreach ($args as $key => $value) {
            $s->where("$key = ?", $value);
        }

        $query = $s->query();
        $results = array();

        while ($result = $query->fetchRow()) {
            $item = new $this;
            $item->setData($result);
            $results[] = $item;
        }

        return $results;

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

        foreach ($this->fieldHandles as $obj) {
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
        $pk = $this->getPrimaryKey();
        return ($this->$pk !== null);
    }

    public function getDisplayField() {
        return 'title';
    }

    public function getDisplayValue() {
        $field = $this->getDisplayField();
        return $this->$field;
    }

    public function getPrimaryKey() {
        return $this->getItemName() . '_id';
    }

    public function getItemName() {
        return strtolower(get_class($this));
    }

    public function getTableName() {
        return strtolower($this->_pluralize(get_class($this)));
    }

    private function _pluralize($str) {
        // needs work...
        return $str . 's';
    }

}

?>
