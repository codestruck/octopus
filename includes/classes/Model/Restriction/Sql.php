<?php

/**
 * An implementation of Octopus_Model_Restriction that just adds literal SQL
 * to a WHERE clause.
 */
class Octopus_Model_Restriction_Sql implements Octopus_Model_Restriction {

    private $sql, $params;

    public function __construct($sql, Array $params = array()) {


        $this->sql = $sql;
        $this->params = $params;
    }

    public function getSql(Octopus_DB_Select $s, Array &$params) {

        if (!$this->sql) {
            return '';
        }

        foreach($this->params as $p) {
            $params[] = $p;
        }

        return "({$this->sql})";
    }

}

?>