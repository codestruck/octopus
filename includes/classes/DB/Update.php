<?php

class Octopus_DB_Update extends Octopus_DB_Helper {

    function Octopus_DB_Update($sql = null, $params = array()) {
        parent::Octopus_DB_Helper($sql, $params);
    }

    function getSql() {

        if ($this->sql) {
            $this->passParams = $this->params;
            return $this->sql;
        }

        $this->passParams = array();

        $sql = 'UPDATE ';

        $sql .= $this->table;
        $sql .= $this->_buildSet();
        $where = $this->_buildWhere();

        if ($where == '') {
            // don't allow updates with no where clause
            return '';
        } else {
            $sql .= $where;
        }

        return $sql;
    }

}

?>
