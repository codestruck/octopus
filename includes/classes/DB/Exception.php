<?php

Octopus::loadClass('Octopus_Exception');

class Octopus_DB_Exception extends Octopus_Exception {

    /**
     * Generates a new Exception for the given SQL query.
     */
    public static function forSql($sql, $params = array(), $message = '') {

        $niceSql = normalize_sql($sql, $params);

        $fullMessage = "SQL query failed: \"$sql\".";
        if ($message) {
            $fullMessage .= "
$message";
        }

        return new Octopus_DB_Exception($fullMessage);


    }

}

?>
