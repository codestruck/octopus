<?php

class NaturalIdProduct extends Octopus_Model {

	protected $primaryKey = array(
		'my_natural_id' => 'numeric'
	);

	protected $fields = array(
		'name',
		'category' => array('type' => 'hasOne', 'model' => 'NaturalIdCategory'),
		'favorite_users' => array('type' => 'hasMany', 'model' => 'NaturalIdUser', 'key' => 'favorite_product'),
		'created',
		'updated'
	);

}

class NaturalIdCategory extends Octopus_Model {

	protected $fields = array(
		'name',
	);

}

class NaturalIdUser extends Octopus_Model {

	protected $fields = array(
		'favorite_product' => array('type' => 'hasOne', 'model' => 'NaturalIdProduct'),
		'cart_product' => array('type' => 'manyToMany', 'model' => 'NaturalIdProduct'),
		'name'
	);

}

class NaturalIdTest extends Octopus_App_TestCase {

	function setUp() {

		parent::setUp();

		Octopus_DB_Schema_Model::makeTable('NaturalIdProduct');
		Octopus_DB_Schema_Model::makeTable('NaturalIdCategory');
		Octopus_DB_Schema_Model::makeTable('NaturalIdUser');

		$db = Octopus_DB::singleton();
		$db->query('TRUNCATE TABLE natural_id_products');
		$db->query('TRUNCATE TABLE natural_id_categories');
		$db->query('TRUNCATE TABLE natural_id_users');
		$db->query('TRUNCATE TABLE natural_id_product_natural_id_user_join');

	}

	function testIdAliasForSingleColNaturalId() {

		$obj = new NaturalIdProduct();
		$obj->id = 1337;
		$this->assertEquals(1337, $obj->id);
		$this->assertEquals(1337, $obj->my_natural_id, 'alias works for single-col natural ids');

	}

	function testSaveFailsWhenMissingNaturalId() {

		$obj = new NaturalIdProduct();
		$obj->name = 'foo';
		$this->assertFalse($obj->save(), 'Save fails w/o natural id assigned');

	}

	function testSaveSucceedsWithNaturalIdPresent() {

		$obj = new NaturalIdProduct();
		$obj->my_natural_id = 1337;
		$obj->name = __METHOD__;
		$this->assertEquals(1337, $obj->save(), 'save succeeds');

		$obj = NaturalIdProduct::get(1337);
		$this->assertTrue(!!$obj, 'object found');
		$this->assertEquals(1337, $obj->my_natural_id);
		$this->assertEquals(__METHOD__, $obj->name);


	}

	function testGetByNaturalIdInCtor() {

		$obj = new NaturalIdProduct();
		$obj->name = __METHOD__;
		$obj->id = 1337;
		$this->assertEquals(1337, $obj->save(), 'save succeeds');

		$obj = new NaturalIdProduct(1337);
		$this->assertEquals(__METHOD__, $obj->name, 'object found by natural id passed to ctor');

	}

	function testGetByNaturalId() {

		$obj = new NaturalIdProduct();
		$obj->name = __METHOD__;
		$obj->id = 1337;
		$this->assertEquals(1337, $obj->save(), 'save succeeds');

		$obj = NaturalIdProduct::get(1337);
		$this->assertTrue(!!$obj, 'object found');
		$this->assertEquals(1337, $obj->id);
		$this->assertEquals(__METHOD__, $obj->name);

	}

	function testSaveUpdatesExistingRecord() {

		$obj = new NaturalIdProduct();
		$obj->id = 1337;
		$obj->name = 'old name';
		$this->assertEquals(1337, $obj->save(), 'save succeeds');

		$obj = new NaturalIdProduct();
		$obj->id = 1337;
		$obj->name = 'new name';
		$this->assertEquals(1337, $obj->save(), 'overwrite succeeds');

		$this->assertEquals(1, count(NaturalIdProduct::all()), 'only 1 record in db');

		$obj = NaturalIdProduct::get(1337);
		$this->assertEquals('new name', $obj->name);

	}

	function testDeleteSingleObject() {

		$obj = new NaturalIdProduct();
		$obj->id = 1337;
		$obj->name = __METHOD__;
		$this->assertEquals(1337, $obj->save(), 'save succeeds');

		$obj->delete();
		$this->assertEquals(0, count(NaturalIdProduct::all()));

		$this->assertEquals(1337, $obj->id, 'id remains after delete');
		$this->assertEquals(__METHOD__, $obj->name, 'name remains after delete');

	}

	function testDeleteMultipleInstances() {

		$a = new NaturalIdProduct();
		$a->id = 1337;
		$a->name = __METHOD__;
		$this->assertEquals(1337, $a->save(), 'save succeeds');

		$b = new NaturalIdProduct(1337);
		$this->assertTrue($a->eq($b), 'two instances of same record are equal');

		$a->delete();
		$this->assertEquals(0, count(NaturalIdProduct::all()), 'record deleted');

		// since name was lazy loaded, it should not be set now
		$this->assertEquals('', $b->name);

	}

	function testMigrate() {

		$r = new Octopus_DB_Schema_Reader('natural_id_products');
		$fields = $r->getFields();

		$this->assertArrayNotHasKey('natural_id_product_id', $fields, 'no default primary key col present');
		$this->assertArrayHasKey('my_natural_id', $fields, 'my_natural_id field found');

		$naturalIdField = $fields['my_natural_id'];
		$this->assertEquals('PRIMARY', $naturalIdField['index']);
		$this->assertEquals('NOT NULL', $naturalIdField['options']);

		$r = new Octopus_DB_Schema_Reader('natural_id_users');
		$fields = $r->getFields();

		$expected = $naturalIdField;
		$actual = $fields['favorite_product_id'];

		unset($expected['field']);
		unset($actual['field']);
		$expected['index'] = 'INDEX';

		$this->assertEquals($expected, $actual);

		$r = new Octopus_DB_Schema_Reader('natural_id_product_natural_id_user_join');
		$fields = $r->getFields();
		//dump_r($fields);

		// TODO: test that there's a 2-col primary key
	}

	function testHasOne() {

		$product = new NaturalIdProduct(array(
			'my_natural_id' => 1337,
			'name' => __METHOD__,
		));

		$user = new NaturalIdUser(array(
			'name' => 'user',
			'favorite_product' => $product
		));

		$this->assertTrue(!!$user->save(), 'user save succeeds');

		$this->assertTrue($product->exists(), 'product record found');
		$this->assertTrue($user->exists(), 'user record found');

		$user = new NaturalIdUser($user->id);
		$this->assertTrue($product->eq($user->favorite_product), 'related thing loaded by natural id');


	}

	function testManyToMany() {

		$pen = new NaturalIdProduct(array(
			'my_natural_id' => 8888,
			'name' => 'pen'
		));
		$this->assertEquals(8888, $pen->id);
		$this->assertTrue(!!$pen->save(), 'pen save succeeds');

		$pencil = new NaturalIdProduct(array(
			'my_natural_id' => 9999,
			'name' => 'pencil'
		));
		$this->assertEquals(9999, $pencil->id);
		$this->assertTrue(!!$pencil->save(), 'pencil save succeeds');

		$user = new NaturalIdUser(array(
			'name' => __METHOD__
		));
		$user->save();

		$user->addCartProduct($pen);
		$user->addCartProduct($pencil);

		$this->assertTrue($user->hasCartProduct($pen), 'has pen');
		$this->assertTrue($user->hasCartProduct($pencil), 'has pencil');

		$s = new Octopus_DB_Select();
		$s->table('natural_id_product_natural_id_user_join');
		$this->assertEquals(
			array(
				array(
					'natural_id_user_id' => '1',
					'my_natural_id' => '8888',
				),
				array(
					'natural_id_user_id' => '1',
					'my_natural_id' => '9999',
				)
			),
			$s->fetchAll()
		);

		$user->removeCartProduct($pen);

		$this->assertFalse($user->hasCartProduct($pen), 'pen removed');
		$this->assertTrue($user->hasCartProduct($pencil), 'has pencil');

		$s = new Octopus_DB_Select();
		$s->table('natural_id_product_natural_id_user_join');
		$this->assertEquals(
			array(
				array(
					'natural_id_user_id' => '1',
					'my_natural_id' => '9999',
				)
			),
			$s->fetchAll()
		);

		$user->addCartProduct($pen);
		$this->assertTrue($user->hasCartProduct($pen), 'has pen');
		$this->assertTrue($user->hasCartProduct($pencil), 'has pencil');

		$s = new Octopus_DB_Select();
		$s->table('natural_id_product_natural_id_user_join');
		$this->assertEquals(
			array(
				array(
					'natural_id_user_id' => '1',
					'my_natural_id' => '9999',
				),
				array(
					'natural_id_user_id' => '1',
					'my_natural_id' => '8888',
				),
			),
			$s->fetchAll()
		);

		$user->removeAllCartProducts();
		$this->assertFalse($user->hasCartProduct($pen), 'pen removed');
		$this->assertFalse($user->hasCartProduct($pencil), 'pencil removed');

		$s = new Octopus_DB_Select();
		$s->table('natural_id_product_natural_id_user_join');
		$this->assertEquals(
			array(),
			$s->fetchAll()
		);

	}

	function testHasMany() {

		$pen = new NaturalIdProduct(array('my_natural_id' => 9999, 'name' => 'pen'));
		$pen->save();

		$joe = new NaturalIdUser(array('name' => 'joe', 'favorite_product' => $pen));
		$joe->save();

		$bob = new NaturalIdUser(array('name' => 'bob', 'favorite_product' => $pen));
		$bob->save();

		$jim = new NaturalIdUser(array('name' => 'jim'));
		$jim->save();

		$this->assertEquals(2, count($pen->favorite_users), 'correct # of things in hasmany');

		/*
		TODO: Do we not support ->has* on hasmanys?
		$this->assertTrue($pen->hasFavoriteUser($joe));
		$this->assertTrue($pen->hasFavoriteUser($bob));
		$this->assertFalse($pen->hasFavoriteUser($jim));
		*/

		$data = array(
			$joe->id => true,
			$bob->id => true
		);

		foreach($pen->favorite_users as $user) {
			unset($data[$user->id]);
		}

		$this->assertEquals(0, count($data), 'correct users found in hasmany');

		/*
		$pen->removeFavoriteUser($joe);
		$this->assertNull($joe->favorite_product);
		$this->assertEquals(1, count($pen->favorite_users));

		$pen->addFavoriteUser($joe);
		$this->assertEquals($pen, $joe->favorite_product);
		$this->assertEquals(2, count($pen->favorite_users));
		*/

		$pen->addFavoriteUser($jim);
		$this->assertTrue($pen->eq($jim->favorite_product));
		$this->assertEquals(3, count($pen->favorite_users));

	}

}

?>