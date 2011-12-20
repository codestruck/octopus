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
}

?>
