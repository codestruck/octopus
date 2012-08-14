<?php
/**
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_DB_Insert extends Octopus_DB_Helper {

    function __construct($sql = null, $params = null) {
        parent::__construct($sql, $params);
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

