<?php

Octopus::loadClass('Octopus_Model');

class Different extends Octopus_Model {
    protected $table = 'nonstandard';
    protected $fields = array(
        'title' => array(),
        'created',
        'updated',
    );
}

class Differentb extends Octopus_Model {
    protected $primaryKey = 'id';
    protected $fields = array(
        'title' => array(),
        'created',
        'updated',
    );
}

class Differentc extends Octopus_Model {
    protected $primaryKey = 'foobar';
    protected $table = 'randomtable';
    protected $fields = array(
        'title' => array(),
        'created',
        'updated',
    );
}

class Differentd extends Octopus_Model {
    protected $displayField = 'kazi';
    protected $fields = array(
        'kazi' => array(),
        'created',
        'updated',
    );
}

class Category extends Octopus_Model {
    protected $fields = array(
        'name' => array(),
    );
}

/**
 * @group Model
 */
class ModelNonStandardTest extends PHPUnit_Framework_TestCase
{

    function __construct()
    {
        $db =& Octopus_DB::singleton();

        $sql = "CREATE TABLE IF NOT EXISTS nonstandard (
                `different_id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `title` varchar ( 255 ) NOT NULL,
                `created` DATETIME NOT NULL,
                `updated` DATETIME NOT NULL
                )
                ";

        $db->query($sql);

        $sql = "CREATE TABLE IF NOT EXISTS differentbs (
                `different_id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `title` varchar ( 255 ) NOT NULL,
                `created` DATETIME NOT NULL,
                `updated` DATETIME NOT NULL
                )
                ";

        $db->query($sql);

        $sql = "CREATE TABLE IF NOT EXISTS randomtable (
                `foobar` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `title` varchar ( 255 ) NOT NULL,
                `created` DATETIME NOT NULL,
                `updated` DATETIME NOT NULL
                )
                ";

        $db->query($sql);

        $sql = "CREATE TABLE IF NOT EXISTS differentds (
                `differentd_id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `kazi` varchar ( 255 ) NOT NULL,
                `created` DATETIME NOT NULL,
                `updated` DATETIME NOT NULL
                )
                ";

        $db->query($sql);


        $sql = "CREATE TABLE IF NOT EXISTS categories (
                `category_id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `name` varchar ( 255 ) NOT NULL
                )
                ";

        $db->query($sql);

    }

    function __destruct()
    {
        $db =& Octopus_DB::singleton();
        $db->query('DROP TABLE IF EXISTS nonstandard');
        $db->query('DROP TABLE IF EXISTS differentbs');
        $db->query('DROP TABLE IF EXISTS randomtable');
        $db->query('DROP TABLE IF EXISTS differentds');
        $db->query('DROP TABLE IF EXISTS categories');
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

        $other = new Differentd('My Other Name');
        $this->assertEquals(2, $other->differentd_id);
    }

    function testRowNotFound()
    {
        $item = new Different(5);
        $this->assertEquals(null, $item->different_id);
    }

    /**
     * @expectedException Octopus_Model_Exception
     */
    function testUnknownPropertyGet()
    {
        $item = new Different();
        $try = $item->nonexisting;

    }

    /**
     * @expectedException Octopus_Model_Exception
     */
    function testUnknownPropertySet()
    {
        $item = new Different();
        $item->nonexisting = 'foo';

    }

    function testPluralize() {

        $cat = new Category();
        $cat->name = 'foo';
        $cat->save();

        $cat = new Category(1);
        $this->assertEquals('foo', $cat->name);

    }

}
