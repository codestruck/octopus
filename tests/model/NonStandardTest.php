<?php

SG::loadClass('SG_Model');

class Different extends SG_Model {
    static $table = 'nonstandard';
    static $fields = array(
        'title' => array(),
        'created',
        'updated',
    );
}

class Differentb extends SG_Model {
    static $primaryKey = 'id';
    static $fields = array(
        'title' => array(),
        'created',
        'updated',
    );
}

class Differentc extends SG_Model {
    static $primaryKey = 'foobar';
    static $table = 'randomtable';
    static $fields = array(
        'title' => array(),
        'created',
        'updated',
    );
}

class Differentd extends SG_Model {
    static $displayField = 'kazi';
    static $fields = array(
        'kazi' => array(),
        'created',
        'updated',
    );
}

/**
 * @group Model
 */
class ModelNonStandardTest extends PHPUnit_Framework_TestCase
{

    function __construct()
    {
        $db =& SG_DB::singleton();

        $sql = "CREATE TABLE nonstandard (
                `different_id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `title` varchar ( 255 ) NOT NULL,
                `created` DATETIME NOT NULL,
                `updated` DATETIME NOT NULL
                )
                ";

        $db->query($sql);

        $sql = "CREATE TABLE differentbs (
                `different_id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `title` varchar ( 255 ) NOT NULL,
                `created` DATETIME NOT NULL,
                `updated` DATETIME NOT NULL
                )
                ";

        $db->query($sql);

        $sql = "CREATE TABLE randomtable (
                `foobar` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `title` varchar ( 255 ) NOT NULL,
                `created` DATETIME NOT NULL,
                `updated` DATETIME NOT NULL
                )
                ";

        $db->query($sql);

        $sql = "CREATE TABLE differentds (
                `differentd_id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `kazi` varchar ( 255 ) NOT NULL,
                `created` DATETIME NOT NULL,
                `updated` DATETIME NOT NULL
                )
                ";

        $db->query($sql);
    }

    function __destruct()
    {
        $db =& SG_DB::singleton();
        $db->query('DROP TABLE IF EXISTS nonstandard');
        $db->query('DROP TABLE IF EXISTS differentbs');
        $db->query('DROP TABLE IF EXISTS randomtable');
        $db->query('DROP TABLE IF EXISTS differentds');
    }

    function testDifferentTableName()
    {
        $item = new Different();
        $this->assertEquals('nonstandard', $item->getTableName());
        $this->assertEquals('different_id', $item->getPrimaryKey());
    }

    function testDifferentPrimaryKey()
    {
        $item = new Differentb();
        $this->assertEquals('differentbs', $item->getTableName());
        $this->assertEquals('id', $item->getPrimaryKey());
    }

    function testDifferentPrimaryKeyAndTable()
    {
        $item = new Differentc();
        $this->assertEquals('randomtable', $item->getTableName());
        $this->assertEquals('foobar', $item->getPrimaryKey());
    }

    function testUsingNonStandard()
    {
        $item = new Differentc();
        $item->title = 'My Title';
        $item->save();
        $this->assertEquals(1, $item->foobar);

        $other = new Differentc(1);
        $this->assertEquals('My Title', $other->title);
    }

    function testDifferentDisplayField()
    {
        $item = new Differentd();
        $this->assertEquals('kazi', $item->getDisplayField()->getFieldName());
    }

    function testDifferentDisplayValue()
    {
        $item = new Differentd();
        $item->kazi = 'My Name';
        $item->save();

        $this->assertEquals('My Name', $item->getDisplayValue());
        $other = new Differentd(1);
        $this->assertEquals('My Name', $other->getDisplayValue());
    }

    function testFindDifferentDisplayValue()
    {
        $item = new Differentd();
        $item->kazi = 'My Other Name';
        $item->save();

        $other = Differentd::get('My Other Name');
        $this->assertEquals(2, $other->differentd_id);
    }

}
