<?php

require_once 'PHPUnit/Extensions/OutputTestCase.php';

Octopus::loadClass('Octopus_DB_Select');

/**
 * @group DB
 */
class Octopus_DB_Error_Test extends PHPUnit_Extensions_OutputTestCase
{

    function __construct()
    {
        $this->db =& Octopus_DB::singleton();
        $this->logfile = LOG_DIR . 'db.txt';
    }

    /*
define('DB_NONE', 0);
define('DB_LOG_ERRORS', 1);
define('DB_PRINT_ERRORS', 2);
define('DB_LOG_ALL', 4);

db_error_reporting($level);
    */

    function setUp()
    {
        delTree(SITE_DIR . 'w');
        mkdir(SITE_DIR . 'w', 0777);
        mkdir(SITE_DIR . 'w/log', 0777);
        touch($this->logfile);
    }

    function testNoReporting()
    {
        db_error_reporting(DB_NONE);
        $this->expectOutputString('');
        $sql = "SELEC FROM test";

        $query = $this->db->query($sql);

        $this->assertEquals(file_get_contents($this->logfile), '');
    }

    function ztestPrintOnly()
    {
        db_error_reporting(DB_PRINT_ERRORS);
        $this->expectOutputRegex('/SELEC FROM test/');
        $sql = "SELEC FROM test";

        $query = $this->db->query($sql);

        $this->assertEquals(file_get_contents($this->logfile), '');
    }

    function testLogOnly()
    {
        db_error_reporting(DB_LOG_ERRORS);
        $this->expectOutputString('');
        $sql = "SELEC FROM test";

        $query = $this->db->query($sql);

        $logMsg = "/.+ DB ERROR: .+ in query 'SELEC FROM test'/";
        $this->assertRegExp($logMsg, file_get_contents($this->logfile));
    }

    function testCompatFunctions()
    {
        db_error_reporting(DB_LOG_ERRORS);
        $sql = "SELEC FROM test";
        $query = $this->db->query($sql);
        $result = $query->fetchRow();
        $this->assertNull($result);
        $numrows = $query->numRows();
        $this->assertEquals(null, $numrows);
    }


}

?>
