<?php
/**
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_DB_Driver_Mysqli {

    public $handle = null;

    function connect() {

        if ($this->handle === null) {

            $this->handle = new mysqli(DB_hostname, DB_username, DB_password, DB_database);
            $this->database = DB_database;
            $this->connection = $this->handle;

            $this->handle->query("SET NAMES 'utf8'");

        }

    }

    function query($sql, $params = array()) {

        $query = $this->handle->prepare($sql);

        if (!$query) {
            $this->success = false;
            return $query;
        }

        if (count($params)) {
            $bind_params = array();
            $format = '';

            foreach ($params as &$param) {
                $format .= 's';
                $bind_params[] = &$param;
            }
            array_unshift($bind_params, $format);
            call_user_func_array(array($query, 'bind_param'), $bind_params);
        }

        $this->success = $query->execute();

        return $query->get_result();
    }

    function fetchAssoc($query) {
        return $query->fetch_assoc();
    }

    function fetchObject($query) {
        $obj = $query->fetch_object();

        if (!$obj) {
            return false;
        }

        return $obj;
    }

    function fetchAll($query) {
        return $query->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * For DELETE, UPDATE, and INSERT queries, returns
     * the actual numer of rows affected.
     */
    function affectedRows($query) {
        return $this->handle->affected_rows;
    }

    /**
     * For SELECT queries, returns the # of rows in the query.
     */
    function numRows($query) {
        return $query->num_rows;
    }

    function numColumns($query) {
        return $query->num_fields;
    }

    function quote($text) {
        return "'" . $this->handle->escape_string($text) . "'";
    }

    function getId() {
        return $this->handle->insert_id;
    }

    function getError($query) {
        return $this->handle->error;
    }

    function beginTransaction() {
        $this->handle->autocommit(false);
    }

    function commitTransaction() {
        $this->handle->commit();
        $this->handle->autocommit(true);
    }

    function rollbackTransaction() {
        $this->handle->rollback();
        $this->handle->autocommit(true);
    }

}
