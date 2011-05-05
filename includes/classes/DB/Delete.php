<?php

Octopus::loadClass('Octopus_DB');
Octopus::loadClass('Octopus_DB_Helper');

class Octopus_DB_Delete extends Octopus_DB_Helper {

    function Octopus_DB_Delete($sql = null, $params = array()) {
        parent::Octopus_DB_Helper($sql, $params);
    }

    function getSql() {

        if ($this->sql) {
            return $this->sql;
        }

        $sql = 'DELETE FROM ';
        $sql .= $this->table;
        $where = $this->_buildWhere();

        if ($where == '') {
            // don't allow updates with no where clause
            return '';
        } else {
//            $where = $this->_parseParams($where, $this->params);
            $sql .= $where;
        }

        return $sql;
    }

}

?>
