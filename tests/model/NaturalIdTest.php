<?php

class NaturalIdTestObject extends Octopus_Model {

	protected $primaryKey = array(
		'my_natural_id' => 'numeric'
	);

	protected $fields = array(
		'name',
		'created',
		'updated'
	);

}

class NaturalIdTest extends Octopus_App_TestCase {

	function setUp() {

		parent::setUp();

		Octopus_DB_Schema_Model::makeTable('NaturalIdTestObject');

		$db = Octopus_DB::singleton();
		$db->query('TRUNCATE TABLE natural_id_test_objects');

	}

	function testIdAliasForSingleColNaturalId() {

		$obj = new NaturalIdTestObject();
		$obj->id = 1337;
		$this->assertEquals(1337, $obj->id);
		$this->assertEquals(1337, $obj->my_natural_id, 'alias works for single-col natural ids');

	}

	function testSaveFailsWhenMissingNaturalId() {

		$obj = new NaturalIdTestObject();
		$obj->name = 'foo';
		$this->assertFalse($obj->save(), 'Save fails w/o natural id assigned');

	}

	function testSaveSucceedsWithNaturalIdPresent() {

		$obj = new NaturalIdTestObject();
		$obj->my_natural_id = 1337;
		$obj->name = __METHOD__;
		$this->assertEquals(1337, $obj->save(), 'save succeeds');

		$obj = NaturalIdTestObject::get(1337);
		$this->assertTrue(!!$obj, 'object found');
		$this->assertEquals(1337, $obj->my_natural_id);
		$this->assertEquals(__METHOD__, $obj->name);


	}

	function testGetByNaturalIdInCtor() {

		$obj = new NaturalIdTestObject();
		$obj->name = __METHOD__;
		$obj->id = 1337;
		$this->assertEquals(1337, $obj->save(), 'save succeeds');

		$obj = new NaturalIdTestObject(1337);
		$this->assertEquals(__METHOD__, $obj->name, 'object found by natural id passed to ctor');

	}

	function testGetByNaturalId() {

		$obj = new NaturalIdTestObject();
		$obj->name = __METHOD__;
		$obj->id = 1337;
		$this->assertEquals(1337, $obj->save(), 'save succeeds');

		$obj = NaturalIdTestObject::get(1337);
		$this->assertTrue(!!$obj, 'object found');
		$this->assertEquals(1337, $obj->id);
		$this->assertEquals(__METHOD__, $obj->name);

	}

	function testSaveUpdatesExistingRecord() {

		$obj = new NaturalIdTestObject();
		$obj->id = 1337;
		$obj->name = 'old name';
		$this->assertEquals(1337, $obj->save(), 'save succeeds');

		$obj = new NaturalIdTestObject();
		$obj->id = 1337;
		$obj->name = 'new name';
		$this->assertEquals(1337, $obj->save(), 'overwrite succeeds');

		$this->assertEquals(1, count(NaturalIdTestObject::all()), 'only 1 record in db');

		$obj = NaturalIdTestObject::get(1337);
		$this->assertEquals('new name', $obj->name);

	}

	function testDeleteSingleObject() {

		$obj = new NaturalIdTestObject();
		$obj->id = 1337;
		$obj->name = __METHOD__;
		$this->assertEquals(1337, $obj->save(), 'save succeeds');

		$obj->delete();
		$this->assertEquals(0, count(NaturalIdTestObject::all()));

		$this->assertEquals(1337, $obj->id, 'id remains after delete');
		$this->assertEquals(__METHOD__, $obj->name, 'name remains after delete');

	}

	function testDeleteMultipleInstances() {

		$a = new NaturalIdTestObject();
		$a->id = 1337;
		$a->name = __METHOD__;
		$this->assertEquals(1337, $a->save(), 'save succeeds');

		$b = new NaturalIdTestObject(1337);
		$this->assertTrue($a->eq($b), 'two instances of same record are equal');

		$a->delete();
		$this->assertEquals(0, count(NaturalIdTestObject::all()), 'record deleted');

		// since name was lazy loaded, it should not be set now
		$this->assertEquals('', $b->name);

	}

	function testMigrate() {

		$r = new Octopus_DB_Schema_Reader('natural_id_test_objects');
		$fields = $r->getFields();

		$this->assertArrayNotHasKey('natural_id_test_object_id', $fields, 'no default primary key col present');
		$this->assertArrayHasKey('my_natural_id', $fields, 'my_natural_id field found');

		$f = $fields['my_natural_id'];
		$this->assertEquals('PRIMARY', $f['index']);
		$this->assertEquals('NOT NULL', $f['options']);

	}

}

?>