<?php

Octopus::loadClass('Octopus_DB');
Octopus::loadClass('Octopus_Model');

db_error_reporting(DB_PRINT_ERRORS);

class Nail extends Octopus_Model {
    protected $fields = array(
        'name' => array(
            'required' => true
        ),
        'hammer' => array(
            'type' => 'hasMany',
        ),
        'active' => array(
            'type' => 'boolean',
        )
    );
}

class Hammer extends Octopus_Model {
    protected $fields = array(
        'name' => array(
            'required' => true,
        ),
        'slug' => array(
            'type' => 'slug',
            'onEmpty' => 'to_unique_slug',
        ),
        'nail' => array(
            'type' => 'hasOne'
        ),
        'active' => array(
            'type' => 'boolean',
        ),
        'display_order' => array(
            'type' => 'order',
        ),
        'created',
        'updated',
    );
}

class Sledgehammer extends Octopus_Model {
    protected $fields = array(
        'name' => array(
            'required' => true,
        ),
        'favorite_nail' => array(
            'type' => 'hasOne',
            'model' => 'nail',
        ),
    );
}

/**
 * @group Model
 */
class ModelOneToManyTest extends Octopus_DB_TestCase
{
    function __construct()
    {
        parent::__construct('model/relation-data.xml');
    }

    function createTables(&$db)
    {
        $sql = "CREATE TABLE hammers (
                `hammer_id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `name` varchar ( 255 ) NOT NULL,
                `slug` varchar ( 255 ) NOT NULL,
                `nail_id` INT( 10 ) NOT NULL,
                `active` TINYINT NOT NULL,
                `display_order` INT( 10 ) NOT NULL,
                `created` DATETIME NOT NULL,
                `updated` DATETIME NOT NULL
                )
                ";

        $db->query($sql);

        $sql = "CREATE TABLE nails (
                `nail_id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `name` varchar ( 255 ) NOT NULL,
                `active` TINYINT NOT NULL
                )
                ";

        $db->query($sql);

        $sql = "CREATE TABLE sledgehammers (
                `sledgehammer_id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `name` varchar ( 255 ) NOT NULL,
                `favorite_nail_id` INT( 10 ) NOT NULL
                )
                ";

        $db->query($sql);
    }

    function dropTables(&$db)
    {
        $db =& Octopus_DB::singleton();
        $db->query('DROP TABLE IF EXISTS hammers');
        $db->query('DROP TABLE IF EXISTS nails');
        $db->query('DROP TABLE IF EXISTS sledgehammers');
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

}
