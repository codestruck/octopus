<?php

Octopus::loadClass('Octopus_DB');
Octopus::loadClass('Octopus_Model');

db_error_reporting(DB_PRINT_ERRORS);

/**
 * @group Model
 */
class ModelEscapeTest extends Octopus_DB_TestCase
{
    function __construct()
    {
        parent::__construct('model/relation-many-data.xml');
    }

    function createTables(&$db)
    {
        Octopus_DB_Schema_Model::makeTable('product');
        Octopus_DB_Schema_Model::makeTable('group');

        Octopus_DB_Schema_Model::makeTable('hammer');
        Octopus_DB_Schema_Model::makeTable('nail');
        Octopus_DB_Schema_Model::makeTable('sledgehammer');
    }

    function dropTables(&$db)
    {
        $db =& Octopus_DB::singleton();

        $db->query('DROP TABLE IF EXISTS groups');
        $db->query('DROP TABLE IF EXISTS products');
        $db->query('DROP TABLE IF EXISTS group_product_join');

        $db->query('DROP TABLE IF EXISTS hammers');
        $db->query('DROP TABLE IF EXISTS nails');
        $db->query('DROP TABLE IF EXISTS sledgehammers');
    }

    function testEscapeName() {
        $str = '<b>Name</b>';

        $group = new Group();
        $group->name = $str;
        $group->save();

        $group = new Group(3);
        $this->assertEquals($str, $group->name);

        $group->escape();
        $this->assertEquals(h($str), $group->name);
        $this->assertFalse($group->save());

    }

    function testEscapeRelationName() {
        $str = '<b>Name</b>';

        $group = new Group();
        $group->name = $str;
        $group->save();

        $product = new Product(1);
        $product->addGroup($group);

        $product->escape();
        $this->assertTrue($product->groups->escaped);
        $this->assertTrue($product->groups->where(array('group_id' => 3))->first()->escaped);
        $this->assertEquals(h($str), $product->groups->where(array('group_id' => 3))->first()->name);
        $this->assertFalse($product->groups->where(array('group_id' => 3))->first()->save());

    }

    /**
     * @expectedException Octopus_Model_Exception
     */
    function testEscapeSetError() {
        $str = '<b>Name</b>';

        $group = new Group();
        $group->name = $str;
        $group->save();

        $group = new Group(3);

        $group->escape();
        $this->assertEquals(h($str), $group->name);
        $this->assertFalse($group->save());
        $group->name = 'foobar';

    }

    function testHasOne()
    {
        $str = '<b>Name</b>';

        $hammer = new Hammer();
        $hammer->name = 'New Hammer';
        $hammer->nail = new Nail();
        $hammer->nail->name = $str;
        $hammer->save();

        $hammer->escape();

        $this->assertEquals(h($str), $hammer->nail->name);
    }

    function testHasOneLazyLoad()
    {
        $str = '<b>Name</b>';

        $hammer = new Hammer();
        $hammer->name = 'New Hammer';
        $hammer->nail = new Nail();
        $hammer->nail->name = $str;
        $hammer->save();
        $id = $hammer->hammer_id;

        $hammer = new Hammer($id);
        $hammer->escape();

        $this->assertEquals(h($str), $hammer->nail->name);
    }

    function testHasMany()
    {
        $str = '<b>Name</b>';

        $nail = new Nail();
        $nail->name = 'foo';
        $nail->save();
        $id = $nail->nail_id;

        $hammer = new Hammer();
        $hammer->name = $str;
        $nail->addHammer($hammer);

        $nail->escape();

        $this->assertEquals(h($str), $nail->hammers->first()->name);
    }

    function testHasManyLazyLoad()
    {
        $str = '<b>Name</b>';

        $nail = new Nail();
        $nail->name = 'foo';
        $nail->save();
        $id = $nail->nail_id;

        $hammer = new Hammer();
        $hammer->name = $str;
        $nail->addHammer($hammer);

        $nail = new Nail($id);
        $nail->escape();

        $this->assertEquals(h($str), $nail->hammers->first()->name);
    }

}

