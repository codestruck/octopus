<?php

SG::loadClass('SG_DB');
SG::loadClass('SG_DB_Helper');

class SG_DB_Insert extends SG_DB_Helper {

    function SG_DB_Insert($sql = null, $params = null) {
        parent::SG_DB_Helper($sql, $params);
    }

    function getSql() {

        if ($this->sql) {
            $this->passParams = $this->params;
            return $this->sql;
        }

        $this->passParams = array();

        $sql = 'INSERT INTO ';

        $sql .= $this->table;
        $sql .= $this->_buildSet();

        return $sql;
    }

}

?>
