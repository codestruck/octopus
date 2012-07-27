<?php

class Octopus_DB_Error {

    function __construct($error, $sql, $params) {

        $this->dateString = 'D, d M Y H:i:s T';
        $this->error = $error;
        $this->sql = $sql;
        $this->params = $params;
        $this->success = false;

        $this->handleError();
    }

    function fetchInto() {
    }

    function fetchRow() {
    }

    function numRows() {
    }

    function handleError() {
        global $DB_ERROR_REPORTING;
        if (!isset($DB_ERROR_REPORTING)) {
            $DB_ERROR_REPORTING = DB_NONE;
        }

        if ($DB_ERROR_REPORTING & DB_PRINT_ERRORS) {
            $this->printError();
        }

        if ($DB_ERROR_REPORTING & DB_LOG_ERRORS) {
            $this->logError();
        }

    }

    function logError() {

        $msg = sprintf("%s DB ERROR: %s in query '%s'", date($this->dateString), $this->error, $this->sql);

        $logDir = get_option('LOG_DIR');
        if (!$logDir) {
            $logDir = get_option('OCTOPUS_PRIVATE_DIR') . 'log/';
            if (!is_dir($logDir)) {
                mkdir($logDir, 0777, true);
            }
        }

        $logger = new Octopus_Logger_File($logDir . 'db.txt');
        $logger->log($msg);
    }

    function printError() {

        throw Octopus_DB_Exception::forSql($this->sql, $this->params, $this->error);

    }
}

