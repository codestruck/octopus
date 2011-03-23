<?php

SG::loadClass('SG_DB');
SG::loadClass('SG_Model');

db_error_reporting(DB_PRINT_ERRORS);

class Nail extends SG_Model {
    static $fields = array(
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

class Hammer extends SG_Model {
    static $fields = array(
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

/**
 * @group Model
 */
class ModelOneToManyTest extends SG_DB_TestCase
{
    function zzsetUp()
    {
        $this->markTestSkipped('Not implemented yet');
    }

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
    }

    function dropTables(&$db)
    {
        $db =& SG_DB::singleton();
        $db->query('DROP TABLE IF EXISTS hammers');
        $db->query('DROP TABLE IF EXISTS nails');
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

}
