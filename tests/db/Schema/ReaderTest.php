<?php

/**
 * @group schema
 * @group DB
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_DB_Schema_Reader_Test extends PHPUnit_Framework_TestCase
{
    function setUp() {
        $db = Octopus_DB::singleton();

        $sql = "DROP TABLE IF EXISTS test";
        $db->query($sql);
        $sql = "DROP TABLE IF EXISTS test_innodb";
        $db->query($sql);
        $sql = "DROP TABLE IF EXISTS test_myisam";
        $db->query($sql);

        $sql = "CREATE TABLE test (
`test_id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
`test_name` VARCHAR( 128 ) NOT NULL,
`test_uname` VARCHAR( 128 ) NOT NULL,
INDEX (`test_name`),
UNIQUE (`test_uname`)
)
";
        $db->query($sql);

        $sql = "CREATE TABLE test_innodb (
`test_id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY
) ENGINE=InnoDB;";
        $db->query($sql);

        $sql = "CREATE TABLE test_myisam (
`test_id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY
) ENGINE=MyISAM;";
        $db->query($sql);

    }

    function tearDown() {
        $db = Octopus_DB::singleton();

        $sql = "DROP TABLE IF EXISTS test";
        $db->query($sql);
    }

    function testCreateSimple()
    {
        $r = new Octopus_DB_Schema_Reader('test');
        $fields = $r->getFields();

        $this->assertEquals(3, count($fields));

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
        $this->assertEquals('1', $indexes[1]['Seq_in_index']);
        $this->assertEquals('test_uname', $indexes[1]['Key_name']);
        $this->assertEquals('1', $indexes[2]['Seq_in_index']);
        $this->assertEquals('test_name', $indexes[2]['Key_name']);
    }

    function testReadFieldsWithIndex() {
        $r = new Octopus_DB_Schema_Reader('test');
        $fields = $r->getFields();

        $this->assertEquals('PRIMARY', $fields['test_id']['index']);
        $this->assertEquals('INDEX', $fields['test_name']['index']);
        $this->assertEquals('UNIQUE', $fields['test_uname']['index']);
    }

    function testReadEngine() {
        $r = new Octopus_DB_Schema_Reader('test_innodb');
        $this->assertEquals('InnoDB', $r->getEngine());
        $r = new Octopus_DB_Schema_Reader('test_myisam');
        $this->assertEquals('MyISAM', $r->getEngine());
    }

}
