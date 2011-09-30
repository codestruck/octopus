<?php

/**
 * PHPUnit test case
 */
require_once 'PHPUnit/Framework/TestCase.php';

/**
 * @group DB
 */
class Octopus_DB_Test extends PHPUnit_Framework_TestCase
{
    function testLoad()
    {
        $this->assertTrue(class_exists('Octopus_DB'));
    }

    function testSingleton()
    {

        $db =& Octopus_DB::singleton();
        $db->handle = null;
        $db =& Octopus_DB::singleton();

        $this->assertTrue($db->driver->handle !== null);
    }

    function testSingletonEquality()
    {

        $db =& Octopus_DB::singleton();
        $db2 =& Octopus_DB::singleton();

        $this->assertTrue($db === $db2);
    }

    function testInternalQueryCount() {

        $db = Octopus_DB::singleton();

        $current = $db->queryCount;
        $db->query('SELECT 1', true);
        $this->assertEquals($current + 1, $db->queryCount);

    }

}
