<?php

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class DateTimeTest extends Octopus_App_TestCase {

    function testDateTimeEmptyStringByDefault() {

        $m = new DateTimeTestModel();
        $this->assertSame('', $m->lunchtime);

    }

    function testDateTimeStandardFormatting() {

        $now = time();

        $m = new DateTimeTestModel();
        $m->lunchtime = $now;
        $this->assertSame(date('Y-m-d H:i:s', $now), $m->lunchtime);

    }

    function testDateTimeHandlesAllZeroes() {

        $m = new DateTimeTestModel();
        $m->lunchtime = '0000-00-00 00:00:00';
        $this->assertSame('', $m->lunchtime);

        $m->lunchtime = '0000-00-00';
        $this->assertSame('', $m->lunchtime);

    }


    function testDateEmptyStringByDefault() {

        $m = new DateTimeTestModel();
        $this->assertSame('', $m->birthdate);

    }

    function testDateStandardFormatting() {

        $now = time();

        $m = new DateTimeTestModel();
        $m->birthdate = $now;
        $this->assertSame(date('Y-m-d', $now), $m->birthdate);

    }

    function testDateHandlesAllZeroes() {

        $m = new DateTimeTestModel();
        $m->birthdate = '0000-00-00 00:00:00';
        $this->assertSame('', $m->birthdate);

        $m->birthdate = '0000-00-00';
        $this->assertSame('', $m->birthdate);

    }

    function testIncomingZeroDateTimes() {

        Octopus_DB_Schema_Model::makeTable('DateTimeTestModel');
        $db = Octopus_DB::singleton();
        $db->query('TRUNCATE TABLE date_time_test_models');

        $i = new Octopus_DB_Insert();
        $i->table('date_time_test_models');
        $i->set('name' , 'zerotest');
        $i->execute();

        $m = new DateTimeTestModel($i->getId());

        $this->assertSame('', $m->lunchtime);
    }

    function testIncomingZeroDates() {

        Octopus_DB_Schema_Model::makeTable('DateTimeTestModel');
        $db = Octopus_DB::singleton();
        $db->query('TRUNCATE TABLE date_time_test_models');

        $i = new Octopus_DB_Insert();
        $i->table('date_time_test_models');
        $i->set('name' , 'zerotest');
        $i->execute();

        $m = new DateTimeTestModel($i->getId());

        $this->assertSame('', $m->birthdate);
    }

    function testRestrictZeroDateTime() {

        Octopus_DB_Schema_Model::makeTable('DateTimeTestModel');
        $db = Octopus_DB::singleton();
        $db->query('TRUNCATE TABLE date_time_test_models');

        $i = new Octopus_DB_Insert();
        $i->table('date_time_test_models');
        $i->set('name' , 'zero');
        $i->execute();

        $zero = new DateTimeTestModel($i->getId());

        $i = new Octopus_DB_Insert();
        $i->table('date_time_test_models');
        $i->set('name' , 'something');
        $i->setNow('lunchtime');
        $i->execute();

        $something = new DateTimeTestModel($i->getId());

        $this->assertFalse(!!$zero->lunchtime);
        $this->assertTrue(!!$something->lunchtime);

        foreach(array('>', '!=') as $op) {

        	foreach(array(0, '0000-00-00 00:00:00') as $zeroValue) {

	        	$models = DateTimeTestModel::find(array(
	        		"lunchtime $op" => 0
		        ));

		        $this->assertEquals(1, count($models), "$op $zeroValue");
		        $this->assertTrue($something->eq($models->first()), "$op $zeroValue");
		    }
        }

        $models = DateTimeTestModel::find(array(
        	'lunchtime >=' => 0
	    ));
	    $this->assertEquals(2, count($models));


    }

}

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class DateTimeTestModel extends Octopus_Model {
    protected $fields = array(
        'name',
        'lunchtime' => 'datetime',
        'birthdate' => 'date'
    );
}
