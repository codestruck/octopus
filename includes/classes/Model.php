<?php

SG::loadClass('SG_DB_Insert');
SG::loadClass('SG_DB_Update');
SG::loadClass('SG_DB_Select');
SG::loadClass('SG_DB_Delete');

class SG_Model {

    public function __construct($id = null) {
        if ($id) {
            $item = $this->findOne($this->getPrimaryKey(), $id);
            $this->setData($item);
        }
    }

    public function __get($var) {

    }

    protected function setData($data) {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }

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

        $i = new SG_DB_Insert();
        $i->table($this->getTableName());

        foreach ($this as $var => $value) {
            // TODO: check for underscore variables?
            $i->set($var, $value);
        }

        $i->execute();
        $pk = $this->getPrimaryKey();
        $this->$pk = $i->getId();

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
