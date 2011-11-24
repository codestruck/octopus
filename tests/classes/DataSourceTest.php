<?php

class DataSourceTestModel extends Octopus_Model {

	protected $fields = array(
		'name',
		'age' => 'numeric'
	);

}

class DataSourceTest extends PHPUnit_Framework_TestCase {

	function testArraySort() {

		$array = array(
			array('id' => 1, 'name' => 'joe blow', 'age' => 40),
			array('id' => 2, 'name' => 'jane blow', 'age' => 40),
		);

		$ds = new Octopus_DataSource_Array($array);
		$ds = $ds->sort('name', true);
		$this->assertEquals(array($array[1], $array[0]), $ds->getArray());

		$ds = $ds->sort('name', false);
		$this->assertEquals(array($array[0], $array[1]), $ds->getArray());

		$ds = $ds->unsort('name');
		$this->assertEquals($array, $ds->getArray(), 'unsort works');

		$ds = $ds->sort('age', false);
		$ds = $ds->sort('name', true, false);

		$this->assertTrue($ds->isSortedBy('age', $asc, $index));
		$this->assertFalse($asc, 'age not sorted asc');
		$this->assertEquals(1, $index, 'age sorted after name');

		$this->assertTrue($ds->isSortedBy('name', $asc, $index));
		$this->assertTrue($asc, 'name sorted asc');
		$this->assertEquals(0, $index, 'name sorted first because it was the most recent sort() call');

		$this->assertFalse($ds->isSortedBy('id'), 'not sorted by id');

		$this->assertEquals(array($array[1], $array[0]), $ds->getArray(), "multi-col sort works");

		$ds = $ds->clearSorting();
		$this->assertEquals($array, $ds->getArray(), "clearSorting works");

	}

	function testArrayFilter() {

		$array = array(
			array('id' => 1, 'name' => 'joe blow'),
			array('id' => 2, 'name' => 'jane blow'),
		);

		$ds = new Octopus_DataSource_Array($array);
		$ds = $ds->filter('name', 'jane blow');

		$this->assertEquals(array($array[1]), $ds->getArray());

		$ds = $ds->unfilter('name');
		$this->assertEquals($array, $ds->getArray());

		$ds = $ds->filter('name', 'joe blow');
		$this->assertEquals(1, count($ds));
		$this->assertEquals(array($array[0]), $ds->getArray());

		$ds = $ds->clearFilters();
		$this->assertEquals($array, $ds->getArray());

	}

	function testArraySortAndFilter() {

		$array = array(
			array('id' => 1, 'name' => 'joe blow', 'age' => 21),
			array('id' => 2, 'name' => 'jane blow', 'age' => 21),
			array('id' => 3, 'name' => 'somebody else', 'age' => 44)
		);

		$ds = new Octopus_DataSource_Array($array);
		$ds = $ds->sort('name');
		$ds = $ds->filter('age', 21);

		$this->assertEquals(
			array(array('id' => 2, 'name' => 'jane blow', 'age' => 21), array('id' => 1, 'name' => 'joe blow', 'age' => 21)),
			$ds->getArray()
		);

	}

	function xtestResultSetSort() {

		Octopus_DB_Schema_Model::makeTable('DataSourceTestModel');
		$db = Octopus_DB::singleton();
		$db->query('TRUNCATE TABLE data_source_test_models');

		$joe = new DataSourceTestModel(array('name' => 'joe blow', 'age' => 40));
		$jane = new DataSourceTestModel(array('name' => 'jane blow', 'age' => 40));

		$rs = DataSourceTestModel::all();
		$ds = new Octopus_DataSource_ResultSet($rs);

		$rs->sort('name');
		$this->assertSqlEquals(
			'SELECT * FROM data_source_test_models ORDER BY `name` ASC',
			$ds->getItems()->getSql()
		);

	}


}

?>