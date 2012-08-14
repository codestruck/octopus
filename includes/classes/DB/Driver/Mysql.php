<?php
/**
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_DB_Driver_Mysql {

    function __construct() {
        $this->handle = null;
        $this->queries = array();

        $this->magic_quotes = get_magic_quotes_gpc() && !defined('COMMAND_LINE_SITE_DIR');

    }

    function connect() {

        if ($this->handle === null) {

            $Octopus_username = DB_username;
            $Octopus_password = DB_password;
            $Octopus_hostname = DB_hostname;
            $Octopus_database = DB_database;

            $this->handle = @mysql_connect($Octopus_hostname, $Octopus_username, $Octopus_password);

            if (!$this->handle) {
                $msg = 'Problem connecting to database server.';
                $logger = new Octopus_Logger_File(LOG_DIR . 'db.txt');
                $logger->log($msg);
                die($msg);
            }

            $selectDB = mysql_select_db($Octopus_database);

            if (!$selectDB) {
                $msg = 'Database does not exist, or missing permissions.';
                $logger = new Octopus_Logger_File(LOG_DIR . 'db.txt');
                $logger->log($msg);
                die($msg);
            }

            $this->database = $Octopus_database;
            $this->connection = $this->handle;

            mysql_query("SET NAMES 'utf8'");

        }

    }

    function query($sql, $params = array()) {

        if (count($params)) {
            $sql = $this->_parseParams($sql, $params);
        }

        $query = mysql_query($sql, $this->connection);

        if ($query) {
            $this->success = true;
        } else {
            $this->success = false;
        }

        return $query;
    }

    function _parseParams($sql, $params) {

        if (!is_array($params) && $params !== null) {
            $params = array($params);
        }

        $placeholderCount = substr_count($sql, '?');

        if (is_array($params) && count($params) != $placeholderCount) {
            trigger_error("DB param count does not match sql: <br>\n" . $sql . "<br>\n" .  print_r($params, true));
        }

        if ($params === null) {
            return $sql;
        }

        $sqlParts = explode('?', $sql);
        $sql = '';

        $sql .= array_shift($sqlParts);

        foreach ($params as $param) {

            $arg = $param;
            if (!$this->magic_quotes) {
                $arg = mysql_real_escape_string($arg);
            }
            $arg = "'$arg'";

            $sql .= $arg;
            $sql .= array_shift($sqlParts);
        }

        return $sql;
    }

    function fetchAssoc($query) {
        return mysql_fetch_assoc($query);
    }

    function fetchObject($query) {
        return mysql_fetch_object($query);
    }

    function fetchAll($query) {

        $allResults = array();

        while ($result = $this->fetchAssoc($query)) {
            $allResults[] = $result;
        }

        return $allResults;

    }

    /**
     * For DELETE, UPDATE, and INSERT queries, returns
     * the actual numer of rows affected.
     */
    function affectedRows($query) {
        return mysql_affected_rows($query);
    }

    /**
     * For SELECT queries, returns the # of rows in the query.
     */
    function numRows($query) {
        return mysql_num_rows($query);
    }

    function numColumns($query) {
        return mysql_num_fields($query);
    }

    function quote($text) {
        return "'" . mysql_real_escape_string($text) . "'";
    }

    function getId() {
        $id = mysql_insert_id();
        return $id ? $id : null;
    }

    function getError($query) {
        return mysql_error();
    }

}


