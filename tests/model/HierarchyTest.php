<?php

Octopus::loadClass('Octopus_DB');
Octopus::loadClass('Octopus_Model');

db_error_reporting(DB_PRINT_ERRORS);

class User extends Octopus_Model {
    protected $fields = array(
        'name' => array(
            'required' => true
        ),
        'group' => array(
            'type' => 'hasMany',
        ),
    );
}

class Container extends Octopus_Model {
    protected $fields = array(
        'name' => array(
            'required' => true,
        ),
        'user' => array(
            'type' => 'hasOne',
        ),
        'thing' => array(
            'type' => 'hasMany',
        ),
    );
}

class Thing extends Octopus_Model {
    protected $fields = array(
        'name' => array(
            'required' => true,
        ),
        'container' => array(
            'type' => 'hasOne',
        ),
    );
}

/**
 * @group Model
 */
class HierarchyTest extends Octopus_DB_TestCase
{
    function __construct()
    {
        parent::__construct('model/hierarchy-data.xml');
    }

    function createTables(&$db)
    {
        $sql = "CREATE TABLE users (
                `user_id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `name` varchar ( 255 ) NOT NULL
                )
                ";

        $db->query($sql);

        $sql = "CREATE TABLE containers (
                `container_id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT( 10 ) NOT NULL,
                `name` varchar ( 255 ) NOT NULL
                )
                ";

        $db->query($sql);

        $sql = "CREATE TABLE things (
                `thing_id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `container_id` INT( 10 ) NOT NULL,
                `name` varchar ( 255 ) NOT NULL
                )
                ";

        $db->query($sql);

    }

    function dropTables(&$db)
    {
        $db =& Octopus_DB::singleton();
        $db->query('DROP TABLE IF EXISTS users');
        $db->query('DROP TABLE IF EXISTS containers');
        $db->query('DROP TABLE IF EXISTS things');
    }

    function testDeepChainFind()
    {
        $containers = Container::find(array('user' => 1));
        $things = Thing::find(array('container' => $containers));
        $this->assertEquals(2, count($things));

    }

}

