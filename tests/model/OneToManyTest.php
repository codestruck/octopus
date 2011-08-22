<?php

Octopus::loadClass('Octopus_DB');
Octopus::loadClass('Octopus_Model');

db_error_reporting(DB_PRINT_ERRORS);

/**
 * @group Model
 */
class ModelOneToManyTest extends Octopus_DB_TestCase
{
    function __construct()
    {
        parent::__construct('model/relation-data.xml');
    }

    function testAccessNail()
    {
        $hammer = new Hammer(1);
        $this->assertEquals('Nail 1', $hammer->nail->name);
    }

    function testCreateNail()
    {
        $nailsBefore = table_count('nails');

        $hammer = new Hammer();
        $hammer->name = 'New Hammer';
        $hammer->nail = new Nail();
        $hammer->nail->name = 'New Nail';
        $hammer->save();

        $nailsAfter = table_count('nails');
        $this->assertEquals($nailsBefore + 1, $nailsAfter);
    }

    function testInValidNail()
    {
        $nailsBefore = table_count('nails');
        $hammersBefore = table_count('hammers');

        $hammer = new Hammer();
        $hammer->name = 'New Hammer';
        $hammer->nail = new Nail();
        $this->assertFalse($hammer->save());

        $nailsAfter = table_count('nails');
        $hammersAfter = table_count('hammers');
        $this->assertEquals($nailsBefore, $nailsAfter);
        $this->assertEquals($hammersBefore, $hammersAfter);
    }

    function testSetNailId()
    {
        $hammer = new Hammer();
        $hammer->name = 'Using old nail';
        $hammer->nail = 1;
        $this->assertTrue($hammer->save());

        $this->assertEquals('Nail 1', $hammer->nail->name);
    }

    function testChangenail()
    {
        $hammer = new Hammer(1);
        $this->assertEquals('Nail 1', $hammer->nail->name);
        $hammer->nail = 2;
        $hammer->save();
        $this->assertEquals('Nail 2', $hammer->nail->name);
    }

    function testNailHasHammers()
    {
        $nail = new Nail(1);
        $this->assertEquals(2, count($nail->hammers));
    }

    function testSledgehammer() {
        $hammer = new Sledgehammer(1);
        $this->assertEquals('Nail 1', $hammer->favorite_nail->name);
    }

    function testSledgehammerCreateNail()
    {
        $nailsBefore = table_count('nails');

        $hammer = new Sledgehammer();
        $hammer->name = 'New Sledgehammer';
        $hammer->favorite_nail = new Nail();
        $hammer->favorite_nail->name = 'New Nail';
        $hammer->save();

        $nailsAfter = table_count('nails');
        $this->assertEquals($nailsBefore + 1, $nailsAfter);
    }


    function testUpdateNoNail()
    {
        $hammer = new Hammer(1);
        $this->assertEquals('Grape', $hammer->name);

        $hammer->name = 'NEW NAME';
        $hammer->save();
       $this->assertEquals('NEW NAME', $hammer->name);

        $newhammer = new Hammer(1);
        $this->assertEquals('NEW NAME', $newhammer->name);

    }

    function testResultSetForeachFind() {

        $all = Hammer::find(array('nail' => 1));

        $i = 1;
        foreach ($all as $item) {
            $i++;
        }
        $this->assertEquals(3, $i, 'The foreach loop did not run 2 times');

    }

    function testResultSetForeachFindTwice() {

        $all = Hammer::find(array('nail' => 1));

        $i = 1;
        foreach ($all as $item) {
            $i++;
        }
        $this->assertEquals(3, $i);

        $this->assertEquals(2, count($all));

        $i = 1;
        foreach ($all as $item) {
            $i++;
        }
        $this->assertEquals(3, $i);

    }

    function testHasManyKey() {
        $nail = Nail::get(1);

        $favs = $nail->favorites;
        $this->assertEquals(2, count($favs));
    }

    function testAddHammerObject() {

        $hammer = new Hammer();
        $hammer->name = 'Added Hammer';

        $nail = new Nail();
        $nail->name = 'Added Nail';
        $nail->save();

        $nail->addHammer($hammer);

        $hammer = new Hammer(5);
        $this->assertEquals($nail->nail_id, $hammer->nail_id);
        $this->assertEquals(1, count($nail->hammers));

    }

    function testAddHammerId() {

        $hammer = new Hammer();
        $hammer->name = 'Added Hammer';
        $hammer->nail = 1;
        $hammer->save();

        $nail = new Nail();
        $nail->name = 'Added Nail';
        $nail->save();

        $nail->addHammer($hammer->id);

        $hammer = new Hammer(5);
        $this->assertEquals($nail->nail_id, $hammer->nail_id);
        $this->assertEquals(1, count($nail->hammers));

    }

    function testAddHammerNull() {

        $hammer = new Hammer();
        $hammer->name = 'Added Hammer';
        $hammer->nail = 1;
        $hammer->save();

        $nail = new Nail();
        $nail->name = 'Added Nail';
        $nail->save();

        $nail->addHammer(null);

        $this->assertEquals(0, count($nail->hammers));

    }

    function testAddHammerEmptyArray() {

        $hammer = new Hammer();
        $hammer->name = 'Added Hammer';
        $hammer->nail = 1;
        $hammer->save();

        $nail = new Nail();
        $nail->name = 'Added Nail';
        $nail->save();

        $nail->addHammer(array());

        $this->assertEquals(0, count($nail->hammers));

    }


    /**
     * @expectedException Octopus_Model_Exception
     */
    function testAddHammerUnsavedFail() {

        $hammer = new Hammer();
        $hammer->name = 'Added Hammer';

        $nail = new Nail();
        $nail->name = 'Added Nail';
        $nail->addHammer($hammer);
        $nail->save();

    }

    function testFindStaticCalledClass() {

        $nail = Hammer::find(array(
            'nail' => 1,
        ))->first();

    }

}
