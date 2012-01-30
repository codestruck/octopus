<?php

class DateTimeTestModel extends Octopus_Model {
    protected $fields = array(
        'name',
        'lunchtime' => 'datetime',
        'birthdate' => 'date'
    );
}

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


}

?>