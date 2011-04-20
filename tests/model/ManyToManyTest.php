<?php

SG::loadClass('SG_DB');
SG::loadClass('SG_Model');

db_error_reporting(DB_PRINT_ERRORS);

class Product extends SG_Model {
    protected $fields = array(
        'name' => array(
            'required' => true
        ),
        'group' => array(
            'type' => 'manyToMany',
        )
    );
}

class Group extends SG_Model {
    protected $fields = array(
        'name' => array(
            'required' => true,
        ),
        'product' => array(
            'type' => 'manyToMany',
        )
    );
}

/**
 * @group Model
 */
class ModelManyToManyTest extends SG_DB_TestCase
{
    function __construct()
    {
        parent::__construct('model/relation-many-data.xml');
    }

    function createTables(&$db)
    {
        $sql = "CREATE TABLE products (
                `product_id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `name` varchar ( 255 ) NOT NULL
                )
                ";

        $db->query($sql);

        $sql = "CREATE TABLE groups (
                `group_id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `name` varchar ( 255 ) NOT NULL
                )
                ";

        $db->query($sql);

        $sql = "CREATE TABLE group_product_join (
                `group_id` INT( 10 ) NOT NULL,
                `product_id` INT( 10 ) NOT NULL
                )
                ";

        $db->query($sql);
    }

    function dropTables(&$db)
    {
        $db =& SG_DB::singleton();
        $db->query('DROP TABLE IF EXISTS groups');
        $db->query('DROP TABLE IF EXISTS products');
        $db->query('DROP TABLE IF EXISTS group_product_join');
    }

    function testGroupCount()
    {
        $product = new Product(1);
        $this->assertEquals(1, count($product->groups));
    }

    function testProductCount()
    {
        $group = new Group(1);
        $this->assertEquals(3, count($group->products));
    }
    
}
