<?php
/**
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_DB_Exception extends Octopus_Exception {

    /**
     * Generates a new Exception for the given SQL query.
     */
    public static function forSql($sql, $params = array(), $message = '') {

        $fullMessage = "SQL query failed: \"$sql\".";
        if ($message) {
            $fullMessage .= "
$message";
        }

        return new Octopus_DB_Exception($fullMessage);

    }

}
