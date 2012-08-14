<?php
/**
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_DB_Delete extends Octopus_DB_Helper {

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

