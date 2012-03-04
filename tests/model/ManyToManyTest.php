<?php

db_error_reporting(DB_PRINT_ERRORS);

class ManyGroup extends Octopus_Model {
    protected $fields = array(
        'name',
        'products' => array(
            'type' => 'many_to_many',
            'model' => 'PluralFieldNameTestProduct',
            'field' => 'groups',
            'relation' => 'product'
        ),
        'important_products' => array(
            'type' => 'many_to_many',
            'model' => 'PluralFieldNameTestProduct',
            'field' => 'important_groups',
            'relation' => 'important',
        ),
    );
}

class PluralFieldNameTestProduct extends Octopus_Model {

    protected $fields = array(
        'name',
        'groups' => array(
            'type' => 'many_to_many',
            'model' => 'ManyGroup',
            'field' => 'products',
            'relation' => 'product',
        ),
        'important_groups' => array(
            'type' => 'many_to_many',
            'model' => 'ManyGroup',
            'field' => 'important_products',
            'relation' => 'important',
        )
    );

}

class OneSided extends Octopus_Model {
    protected $fields = array(
        'name',
        'groups' => array(
            'type' => 'many_to_many',
            'relation' => 'onesided',
        )
    );
}

/**
 * @group Model
 */
class ModelManyToManyTest extends Octopus_DB_TestCase
{
    function __construct()
    {
        parent::__construct('model/relation-many-data.xml');
    }

    function testJoinTableNames() {
        $product = new Product();
        $field = $product->getField('groups');
        $this->assertEquals('group_product_join', $field->getJoinTableName(), 'simple default joins');

        $group = new Group();
        $field = $group->getField('products');
        $this->assertEquals('group_product_join', $field->getJoinTableName(), 'simple default joins');

        $product = new PluralFieldNameTestProduct();
        $field = $product->getField('groups');
        $this->assertEquals('many_group_plural_field_name_test_product_product_join', $field->getJoinTableName());
        $field = $product->getField('important_groups');
        $this->assertEquals('many_group_plural_field_name_test_product_important_join', $field->getJoinTableName());

        $product = new ManyGroup();
        $field = $product->getField('products');
        $this->assertEquals('many_group_plural_field_name_test_product_product_join', $field->getJoinTableName());
        $field = $product->getField('important_products');
        $this->assertEquals('many_group_plural_field_name_test_product_important_join', $field->getJoinTableName());

    }

    function testPluralFieldNameJoinedClass() {

        $product = new PluralFieldNameTestProduct();

        $field = $product->getField('groups');
        $this->assertEquals('ManyGroup', $field->getJoinedModelClass());

        $field = $product->getField('important_groups');
        $this->assertEquals('ManyGroup', $field->getJoinedModelClass());

        $product = new ManyGroup();

        $field = $product->getField('products');
        $this->assertEquals('PluralFieldNameTestProduct', $field->getJoinedModelClass());

        $field = $product->getField('important_products');
        $this->assertEquals('PluralFieldNameTestProduct', $field->getJoinedModelClass());

    }

    function testPluralFieldName() {

        Octopus_DB_Schema_Model::makeTable('PluralFieldNameTestProduct');
        Octopus_DB_Schema_Model::makeTable('ManyGroup');

        $group = new ManyGroup();
        $group->name = "Test Group";
        $group->save();

        $product = new PluralFieldNameTestProduct();
        $product->name = "test product";
        $product->save();

        $product->addGroup($group);
        $this->assertEquals(1, count($product->groups));
        $this->assertTrue($product->hasGroup($group));

        $product->removeGroup($group);
        $this->assertFalse($product->hasGroup($group));

        $product->addGroup($group);
        $product->removeAllGroups();
        $this->assertEquals(0, count($product->groups));
    }

    function testPluralFieldNameSecondary() {

        Octopus_DB_Schema_Model::makeTable('PluralFieldNameTestProduct');
        Octopus_DB_Schema_Model::makeTable('ManyGroup');

        $group = new ManyGroup();
        $group->name = "Test Group";
        $group->save();

        $product = new PluralFieldNameTestProduct();
        $product->name = "Test Product";
        $product->save();

        $product->addImportantGroup($group);
        $this->assertEquals(1, count($product->important_groups));

        $this->assertTrue($product->hasImportantGroup($group));

        $product->removeImportantGroup($group);
        $this->assertFalse($product->hasImportantGroup($group));

        $product->addImportantGroup($group);
        $product->removeAllImportantGroups($group);
        $this->assertEquals(0, count($product->important_groups));
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

    function testAddProductToGroupSetData()
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
        // this call will wipe out existing relations, leaving just these 2
        $group->setData(array('products' => array($product_id, $product_id2)));
        $group->save();

        $g = new Group(2);
        $this->assertEquals(2, count($g->products));
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

    function testRemoveAllProducts()
    {

        $group = new Group(1);
        $this->assertEquals(3, count($group->products));
        $group->removeAllProducts();

        $g = new Group(1);
        $this->assertEquals(0, count($g->products));
    }

    function testRemoveProductById()
    {
        $group = new Group(2);
        $group->removeProduct(2);

        $g = new Group(2);
        $this->assertEquals(0, count($g->products));
    }

    function testHasProductId()
    {
        $group = new Group(1);
        $this->assertTrue($group->hasProduct(1));

        $group = new Group(2);
        $this->assertFalse($group->hasProduct(1));
    }

    function testHasGroupId()
    {
        $product = new Product(1);
        $this->assertTrue($product->hasGroup(1));
        $this->assertFalse($product->hasGroup(2));

        $product = new Product(3);
        $this->assertTrue($product->hasGroup(1));
        $this->assertFalse($product->hasGroup(2));
    }

    function testHasProductObject()
    {
        $product = new Product(1);

        $group = new Group(1);
        $this->assertTrue($group->hasProduct($product));

        $group = new Group(2);
        $this->assertFalse($group->hasProduct($product));
    }

    function testHasGroupObject()
    {
        $product = new Product(1);
        $this->assertTrue($product->hasGroup(new Group(1)));
        $this->assertFalse($product->hasGroup(new Group(2)));

        $product = new Product(3);
        $this->assertTrue($product->hasGroup(new Group(1)));
        $this->assertFalse($product->hasGroup(new Group(2)));
    }

    function testAddProductNull()
    {
        $group = new Group(2);
        $group->addProduct(null);
        $group->save();

        $g = new Group(2);
        $this->assertEquals(1, count($g->products));
    }

    function testAddProductEmptyArray()
    {
        $group = new Group(2);
        $group->addProduct(null);
        $group->save();

        $g = new Group(2);
        $this->assertEquals(1, count($g->products));
    }

    function testOneSided() {

        $this->markTestIncomplete('TODO: implement one-sided many_to_many');

        Octopus_DB_Schema_Model::makeTable('OneSided');

        $group = new Group();
        $group->name = "Test Group";
        $group->save();

        $product = new OneSided();
        $product->name = "test product";
        $product->save();

        $product->addGroup($group);
        $this->assertEquals(1, count($product->groups));
        $this->assertTrue($product->hasGroup($group));

        $product->removeGroup($group);
        $this->assertFalse($product->hasGroup($group));

        $product->addGroup($group);
        $product->removeAllGroups();
        $this->assertEquals(0, count($product->groups));

    }

    function testLoopCallsId() {

        $db = Octopus_DB::singleton();
        $count = $db->queryCount;

        $group = new Group(1);
        $this->assertEquals($count, $db->queryCount, 'pass id to ctor does not result in db query');

        foreach ($group->products as $product) {
            $this->assertTrue($product->id > 0);
        }

        $this->assertEquals($count + 1, $db->queryCount, 'read id from everything in many to many takes 1 query');

    }

    function testLoopCallsPrimaryKey() {

        $db = Octopus_DB::singleton();
        $count = $db->queryCount;

        $group = new Group(1);
        $this->assertEquals($count, $db->queryCount, 'pass id to ctor does not result in db query');

        foreach ($group->products as $product) {
            $this->assertTrue($product->id > 0);
        }

        $this->assertEquals($count + 1, $db->queryCount, 'read id from everything in many to many takes 1 query');

    }

}

