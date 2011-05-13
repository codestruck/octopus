<?php

    error_reporting(E_ALL | E_STRICT);
    define('DEV', true);

    require_once(dirname(__FILE__) . '/../includes/core.php');
    bootstrap();
    error_reporting(E_ALL | E_STRICT);

    $hostname = trim(`hostname`);
    define('TEST_FIXTURE_DIR', dirname(__FILE__) . '/fixtures/');

    require_once('Octopus_DB_TestCase.php');
    require_once('Octopus_App_TestCase.php');

    // put testing helpers somewhere
    function table_count($table) {
        $s = new Octopus_DB_Select();
        $s->table($table);
        $query = $s->query();

        return $query->numRows();
    }

?>
