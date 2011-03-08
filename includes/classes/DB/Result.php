<?php

class SG_DB_Result {

    function SG_DB_Result($driver, $query) {
        $this->success = true;
        $this->driver = $driver;
        $this->query = $query;
        $this->handle = $this->driver->handle;
    }

    function fetchRow() {
        return $this->driver->fetchAssoc($this->query);
    }

    function fetchObject() {
        return $this->driver->fetchObject($this->query);
    }

    function fetchInto(&$arr) {
        $arr = $this->fetchRow();
        return $arr;
    }

    function fetchAll() {
        return $this->driver->fetchAll($this->query);
    }

    function numRows() {
        return $this->driver->numRows($this->query);
    }

    function numColumns() {
        return $this->driver->numColumns($this->query);
    }

    function quote($text) {
        return $this->driver->quote($text);
    }

    function getId() {
        $this->driver->getId();
    }

}

?>
