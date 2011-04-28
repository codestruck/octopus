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

    function testAddProductToGroup()
    {
        $product = new Product();
        $product->name = 'New Product';

        $group = new Group(2);
        $group->addProduct($product);
        $group->save();

        $p = new Product(4);
        $this->assertEquals('New Product', $p->name);

        $g = new Group(2);
        $this->assertEquals(2, count($g->products));
    }

    function testAddProductsToGroup()
    {
        $product = new Product();
        $product->name = 'New Product';

        $product2 = new Product();
        $product2->name = 'New Product2';

        $group = new Group(2);
        $group->addProducts(array($product, $product2));
        $group->save();

        $p = new Product(4);
        $this->assertEquals('New Product', $p->name);

        $p = new Product(5);
        $this->assertEquals('New Product2', $p->name);

        $g = new Group(2);
        $this->assertEquals(3, count($g->products));
    }

    function testAddProductIdToGroup()
    {
        $product = new Product();
        $product->name = 'New Product';
        $product->save();
        $product_id = $product->product_id;

        $group = new Group(2);
        $group->addProduct($product_id);
        $group->save();

        $p = new Product(4);
        $this->assertEquals('New Product', $p->name);

        $g = new Group(2);
        $this->assertEquals(2, count($g->products));
    }

    function testAddProductIdsToGroup()
    {
        $product = new Product();
        $product->name = 'New Product';
        $product->save();
        $product_id = $product->product_id;

        $product2 = new Product();
        $product2->name = 'New Product2';
        $product2->save();
        $product_id2 = $product2->product_id;

        $group = new Group(2);
        $group->addProducts(array($product_id, $product_id2));

        $p = new Product(4);
        $this->assertEquals('New Product', $p->name);

        $p = new Product(5);
        $this->assertEquals('New Product2', $p->name);

        $g = new Group(2);
        $this->assertEquals(3, count($g->products));
    }

    function testRemoveProduct()
    {
        $product = new Product(2);

        $group = new Group(2);
        $group->removeProduct($product);

        $g = new Group(2);
        $this->assertEquals(0, count($g->products));
    }

    function testRemoveProducts()
    {
        $product = new Product(1);
        $product2 = new Product(2);

        $group = new Group(1);
        $group->removeProducts(array($product, $product2));

        $g = new Group(1);
        $this->assertEquals(1, count($g->products));
    }

    function testRemoveProductById()
    {
        $group = new Group(2);
        $group->removeProduct(2);

        $g = new Group(2);
        $this->assertEquals(0, count($g->products));
    }

}

