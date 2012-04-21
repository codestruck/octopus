<?php

/**
 * @group DB
 */
class Octopus_DB_Schema_Test extends PHPUnit_Framework_TestCase
{

    function setUp()
    {
        $db = Octopus_DB::singleton();
        $db->query('DROP TABLE IF EXISTS `new_table`');
        $db->query('DROP TABLE IF EXISTS `renamed_table`');
        $db->query('DROP TABLE IF EXISTS `engine_test`');
    }

    function testAddTable()
    {
        $table = 'new_table';

        $d = new Octopus_DB_Schema();
        $this->assertFalse($d->checkTable($table));

        $t = $d->newTable($table);
        $t->newKey('id');
        $t->create();

        $this->assertTrue($d->checkTable($table));
    }

    function testRemoveTable()
    {
        $table = 'new_table';

        $d = new Octopus_DB_Schema();
        $this->assertFalse($d->checkTable($table));

        $t = $d->newTable($table);
        $t->newKey('id');
        $t->create();

        $this->assertTrue($d->checkTable($table));

        $d->removeTable($table);
        $this->assertFalse($d->checkTable($table));

    }

    function testRenamedTable()
    {
        $table = 'new_table';
        $table2 = 'renamed_table';

        $d = new Octopus_DB_Schema();
        $this->assertFalse($d->checkTable($table));

        $t = $d->newTable($table);
        $t->newKey('id');
        $t->create();

        $this->assertTrue($d->checkTable($table));

        $d->renameTable($table, $table2);
        $this->assertFalse($d->checkTable($table));
        $this->assertTrue($d->checkTable($table2));

    }

    function tableTypes() {
        return array(
            array('', 'MyISAM'),
            array('InnoDB', 'InnoDB'),
            array('MyISAM', 'MyISAM'),
        );
    }

    /**
     * @dataProvider tableTypes
     */
    function testEngineType($type, $expected) {
        $d = new Octopus_DB_Schema();
        $t = $d->newTable('engine_test', $type);
        $t->newKey('id');
        $t->create();

        $db = Octopus_DB::singleton();
        $query = $db->query("SHOW TABLE STATUS WHERE Name = 'engine_test'");
        $result = $query->fetchRow();
        $this->assertEquals($expected, $result['Engine']);

    }

    /**
     * @dataProvider tableTypes
     */
    function testChangeEngineTypeFromMyISAM($type, $expected) {
        $d = new Octopus_DB_Schema();
        $t = $d->newTable('engine_test', 'MyISAM');
        $t->newKey('id');
        $t->create();

        $d = new Octopus_DB_Schema();
        $t = $d->newTable('engine_test', $type);
        $t->newKey('id');
        $t->create();

        $db = Octopus_DB::singleton();
        $query = $db->query("SHOW TABLE STATUS WHERE Name = 'engine_test'");
        $result = $query->fetchRow();
        $this->assertEquals($expected, $result['Engine']);

    }

}

?>
