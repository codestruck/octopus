<?php

/**
 * @group schema
 * @group DB
 */
class Octopus_DB_Schema_Reader_Test extends PHPUnit_Framework_TestCase
{
    function setUp() {
        $db =& Octopus_DB::singleton();

        $sql = "DROP TABLE IF EXISTS test";
        $db->query($sql);

        $sql = "CREATE TABLE test (
`test_id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
`test_name` VARCHAR( 128 ) NOT NULL
)
";
        $db->query($sql);
    }

    function tearDown() {
        $db =& Octopus_DB::singleton();

        $sql = "DROP TABLE IF EXISTS test";
        $db->query($sql);
    }

    function testCreateSimple()
    {
        $r = new Octopus_DB_Schema_Reader('test');
        $fields = $r->getFields();

        $this->assertEquals(2, count($fields));

        $this->assertArrayHasKey('test_id', $fields);
        $this->assertArrayHasKey('test_name', $fields);

        $this->assertEquals('int', $fields['test_id']['type']);
        $this->assertEquals('NOT NULL AUTO_INCREMENT', $fields['test_id']['options']);
        $this->assertEquals('PRIMARY', $fields['test_id']['index']);
    }

    function testReadIndexes() {
        $r = new Octopus_DB_Schema_Reader('test');
        $indexes = $r->getIndexes();

        $this->assertEquals('1', $indexes[0]['Seq_in_index']);
        $this->assertEquals('PRIMARY', $indexes[0]['Key_name']);
    }

}
