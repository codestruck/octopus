<?php

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class DataSourceTest extends PHPUnit_Framework_TestCase {

    function testArraySort() {

        $array = array(
            array('id' => 1, 'name' => 'joe blow', 'age' => 40),
            array('id' => 2, 'name' => 'jane blow', 'age' => 40),
        );

        $ds = new Octopus_DataSource_Array($array);

        $this->assertTrue($ds->isSortable('id'), 'id is sortable');
        $this->assertTrue($ds->isSortable('name'), 'name is sortable');
        $this->assertTrue($ds->isSortable('age'), 'age is sortable');
        $this->assertFalse($ds->isSortable('fake_col'), 'non-existant column is not sortable');

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

        $ds = $ds->unsort();
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

        $ds = $ds->unfilter();
        $this->assertEquals($array, $ds->getArray());

        $ds = $ds->filter('name', 'joe blow');
        $this->assertEquals(1, count($ds));
        $this->assertEquals(array($array[0]), $ds->getArray());

        $ds = $ds->unfilter();
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

    function testArrayLimit() {

        $array = array(
            array('id' => 1, 'name' => 'joe blow', 'age' => 21),
            array('id' => 2, 'name' => 'jane blow', 'age' => 21),
            array('id' => 3, 'name' => 'somebody else', 'age' => 44)
        );

        $ds = new Octopus_DataSource_Array($array);

        $l = $ds->limit(0, 2);
        $this->assertEquals(array_slice($array, 0, 2), $l->getArray());

        $l = $l->unlimit();
        $this->assertEquals($array, $l->getArray());

        $l = $ds->limit(2);
        $this->assertEquals(array_slice($array, 2, 1), $l->getArray());

        $l = $l->limit(1, 5);
        $this->assertEquals(array_slice($array, 1), $l->getArray());


    }

}

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class DataSourceTestModel extends Octopus_Model {

    protected $fields = array(
        'name',
        'age' => 'numeric'
    );

}
