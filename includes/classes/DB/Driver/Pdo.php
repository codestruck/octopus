<?php
class Octopus_DB_Driver_Pdo {

    public $handle = null;
    private $lastSql = '';

    /**
     * Establish a DB connection.
     */
    public function connect() {

        if ($this->handle !== null) {
            return;
        }

        if (!(defined('DB_hostname') && defined('DB_database') && defined('DB_username') && defined('DB_password'))) {
            throw new Octopus_DB_Exception("DB configuration is not available.");
        }

        $this->handle = new PDO(sprintf('mysql:host=%s;dbname=%s', DB_hostname, DB_database), DB_username, DB_password);
        $this->handle->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
        $this->database = DB_database;
        $this->connection = $this->handle;

    }

    /**
     * @return PDOStatement
     */
    function query($sql, $params = array()) {

        $query = $this->handle->prepare($sql);

        try {
            $query->execute($params);
        } catch (PDOException $e) {
            $this->success = false;
            return $query;
        }

        if ($query->errorCode() === '00000') {
            $this->success = true;
        } else {
            $this->success = false;
        }

        return $query;
    }

    function fetchAssoc($query) {
        return $query->fetch(PDO::FETCH_ASSOC);
    }

    function fetchObject($query) {
        return $query->fetchObject();
    }

    function fetchAll($query) {
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    function affectedRows($query) {
        return $query->rowCount();
    }

    function numRows($query) {
        //NOTE: The docs say this is not guaranteed to return the # of
        // rows in a SELECT query, but usually will.
        return $query->rowCount();
    }

    function numColumns($query) {
        return $query->columnCount();
    }

    function quote($text) {
        return $this->handle->quote($text);
    }

    function getId() {
        $id =  $this->handle->lastInsertId();
        return $id ? $id : null;
    }

    function getError($query) {
        $info = $query->errorInfo();
        return $info[2];
    }

}


?>
