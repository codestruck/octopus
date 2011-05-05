<?php

Octopus::loadClass('Octopus_Logger_File');

class Octopus_DB_Driver_Pdo {

    function Octopus_DB_Driver_Pdo() {
        $this->handle = null;
        $this->lastSql = '';
    }

    function connect() {

        if ($this->handle === null) {

            $this->handle = new PDO(sprintf('mysql:host=%s;dbname=%s', DB_hostname, DB_database), DB_username, DB_password);
            $this->handle->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

            if (!$this->handle) {
                $msg = 'Problem connecting to database server.';
                $logger = new Octopus_Logger_File(LOG_DIR . 'db.txt');
                $logger->log($msg);
                die($msg);
            }

            $this->database = DB_database;
            $this->connection = $this->handle;

        }

    }

    /**
     * @return PDOStatement
     */
    function query($sql, $params = array()) {

        if ($sql !== $this->lastSql || preg_match('/select/i', $sql)) {
            $this->lastQuery = $this->handle->prepare($sql);
        }

        $this->lastSql = $sql;

        try {
            $this->lastQuery->execute($params);
        } catch (PDOException $e) {
            $this->success = false;
            return $this->lastQuery;
        }

        if ($this->lastQuery->errorCode() === '00000') {
            $this->success = true;
        } else {
            $this->success = false;
        }

        return $this->lastQuery;
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
