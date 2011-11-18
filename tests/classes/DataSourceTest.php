<?php

Octopus::loadClass('Octopus_DataSource_Array');

class DataSourceTest extends PHPUnit_Framework_TestCase {

	function testArraySort() {

		$array = array(
			array('id' => 1, 'name' => 'joe blow'),
			array('id' => 2, 'name' => 'jane blow'),
		);

		$ds = new Octopus_DataSource_Array($array);

		$sorted = $ds->sort('name', true);
		$this->assertEquals(
			array(array('id' => 2, 'name' => 'jane blow'), array('id' => 1, 'name' => 'joe blow')),
			$sorted->getItems()
		);

	}

	function testArrayFilter() {

		$array = array(
			array('id' => 1, 'name' => 'joe blow'),
			array('id' => 2, 'name' => 'jane blow'),
		);

		$ds = new Octopus_DataSource_Array($array);

		$filtered = $ds->filter('name', 'jane blow');
		$this->assertEquals(
			array(array('id' => 2, 'name' => 'jane blow')),
			$filtered->getItems()
		);

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
			$ds->getItems()
		);

	}



}

?>