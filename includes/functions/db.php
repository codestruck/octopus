<?php

if (!defined('DB_LOG_ERRORS')) {
    define('DB_NONE', 0);
    define('DB_LOG_ERRORS', 1);
    define('DB_PRINT_ERRORS', 2);
    define('DB_LOG_ALL', 4);
}

$DB_ERROR_REPORTING = DB_NONE;

/**
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
function db_error_reporting($level) {
    global $DB_ERROR_REPORTING;
    $old = $DB_ERROR_REPORTING;
    $DB_ERROR_REPORTING = $level;
    return $old;
}

/**
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
function dump_db_log() {

    $db = Octopus_DB::singleton();
    $log = new Octopus_Logger_File(OCTOPUS_PRIVATE_DIR . 'db_query.log');
    $count = count($db->queries);
    $time = round(microtime(true) - $_SERVER['REQUEST_TIME_MILLISECOND'], 3);
    $log->log("\n\n-- $time sec $count QUERIES: {$_SERVER['REQUEST_URI']}\n" . implode("\n", $db->queries));

}
