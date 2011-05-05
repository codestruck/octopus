<?php

Octopus::loadClass('Octopus_Logger_File');

if (!defined('DB_LOG_ERRORS')) {
    define('DB_NONE', 0);
    define('DB_LOG_ERRORS', 1);
    define('DB_PRINT_ERRORS', 2);
    define('DB_LOG_ALL', 4);
}

if (!function_exists('db_error_reporting')) {
    $DB_ERROR_REPORTING = DB_NONE;

    function db_error_reporting($level) {
        global $DB_ERROR_REPORTING;
        $old = $DB_ERROR_REPORTING;
        $DB_ERROR_REPORTING = $level;
        return $old;
    }
}

class Octopus_DB_Error {

    function Octopus_DB_Error($error, $sql) {

        $this->dateString = 'D, d M Y H:i:s T';
        $this->error = $error;
        $this->sql = $sql;
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

        $logger = new Octopus_Logger_File(LOG_DIR . 'db.txt');
        $logger->log($msg);
    }

    function printError() {

        $msg = "<b>DB Error:</b> <code>$this->error</code><br><b>Query:</b> <code>$this->sql</code><br>";
        trigger_error($msg, E_USER_WARNING);

    }
}

?>
