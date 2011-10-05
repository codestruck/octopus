<?php

if (!defined('DB_LOG_ERRORS')) {
    define('DB_NONE', 0);
    define('DB_LOG_ERRORS', 1);
    define('DB_PRINT_ERRORS', 2);
    define('DB_LOG_ALL', 4);
}

$DB_ERROR_REPORTING = DB_NONE;

function db_error_reporting($level) {
    global $DB_ERROR_REPORTING;
    $old = $DB_ERROR_REPORTING;
    $DB_ERROR_REPORTING = $level;
    return $old;
}

?>