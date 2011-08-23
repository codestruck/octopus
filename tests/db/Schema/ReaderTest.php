<?php

Octopus::loadClass('Octopus_DB_Schema_Reader');

/**
 * @group schema
 * @group DB
 */
class Octopus_DB_Schema_Reader_Test extends PHPUnit_Framework_TestCase
{
    function testCreateSimple()
    {
        $db =& Octopus_DB::singleton();

        $sql = "DROP TABLE IF EXISTS test";
        $db->query($sql);

        $sql = "CREATE TABLE test (
`test_id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
`test_name` VARCHAR( 128 ) NOT NULL
)
";
        $db->query($sql);

        $r = new Octopus_DB_Schema_Reader('test');
        $fields = $r->getFields();

        $this->assertEquals(2, count($fields));

        $this->assertArrayHasKey('test_id', $fields);
        $this->assertArrayHasKey('test_name', $fields);

        $this->assertEquals('int', $fields['test_id']['type']);
        $this->assertEquals('NOT NULL AUTO_INCREMENT', $fields['test_id']['options']);
        $this->assertEquals('PRIMARY KEY', $fields['test_id']['index']);
    }

}

?>
