<?php

Octopus::loadClass('Octopus_DB_Select');

/**
 * @group DB
 */
class Octopus_DB_Error_Test extends Octopus_App_TestCase
{

    function __construct()
    {
        $this->db =& Octopus_DB::singleton();
    }

    function setUp()
    {
        parent::setUp();

        $this->logFile = $this->getPrivateDir() . 'log/db.txt';
        recursive_touch($this->logFile);
    }

    function testNoReporting()
    {
        db_error_reporting(DB_NONE);
        $this->expectOutputString('');
        $sql = "SELEC FROM test";

        $query = $this->db->query($sql);

        $this->assertEquals(file_get_contents($this->logFile), '');
    }

    function ztestPrintOnly()
    {
        db_error_reporting(DB_PRINT_ERRORS);
        $this->expectOutputRegex('/SELEC FROM test/');
        $sql = "SELEC FROM test";

        $query = $this->db->query($sql);

        $this->assertEquals(file_get_contents($this->logFile), '');
    }

    function testLogOnly()
    {
        db_error_reporting(DB_LOG_ERRORS);
        $this->expectOutputString('');
        $sql = "SELEC FROM test";

        $query = $this->db->query($sql);

        $logMsg = "/.+ DB ERROR: .+ in query 'SELEC FROM test'/";
        $this->assertRegExp($logMsg, file_get_contents($this->logFile));
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
