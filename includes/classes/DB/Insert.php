<?php

Octopus::loadClass('Octopus_DB');
Octopus::loadClass('Octopus_DB_Helper');

class Octopus_DB_Insert extends Octopus_DB_Helper {

    function Octopus_DB_Insert($sql = null, $params = null) {
        parent::Octopus_DB_Helper($sql, $params);
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
