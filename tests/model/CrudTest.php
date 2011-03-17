<?php

require_once('PHPUnit/Extensions/Database/TestCase.php');

SG::loadClass('SG_DB');
SG::loadClass('SG_Model');

db_error_reporting(DB_PRINT_ERRORS);

class Minpost extends SG_Model {
    static $fields = array(
        'title' => array(
            'required' => true,
        ),
        'slug' => array(
            'type' => 'slug', // implies hidden input
            //'onCreate' => 'to_unique_slug',
            //'onSave' => 'to_slug',
            'onEmpty' => 'to_unique_slug',
            //'' => 'dealwith'
        ),
        'body' => array(
            'type' => 'html',
            'sanitize' => 'mce_cleanup',
        ),
        'author' => array(
            'type' => 'has_one'
        ),
        'active' => array(
            'type' => 'toggle',
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
class ModelCrudLoadTest extends PHPUnit_Extensions_Database_TestCase
{
    protected function getConnection()
    {
        $db = SG_DB::singleton();
        $pdo = $db->driver->handle;
        return $this->createDefaultDBConnection($pdo, $db->driver->database);
    }

    function getDataSet()
    {
        return $this->createFlatXMLDataSet(TEST_FIXTURE_DIR . '/model/crud-data.xml');
    }

    function __construct()
    {
        $db =& SG_DB::singleton();
        $db->query('DROP TABLE IF EXISTS minposts');

        $sql = "CREATE TABLE minposts (
                `minpost_id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `title` varchar ( 255 ) NOT NULL,
                `slug` varchar ( 255 ) NOT NULL,
                `body` text NOT NULL,
                `author_id` INT( 10 ) NOT NULL,
                `active` TINYINT NOT NULL,
                `display_order` INT( 10 ) NOT NULL,
                `created` DATETIME NOT NULL,
                `updated` DATETIME NOT NULL
                )
                ";

        $db->query($sql);

    }

    function __destruct()
    {
        $db =& SG_DB::singleton();
        $db->query('DROP TABLE IF EXISTS minposts');
    }

    function testTableName()
    {
        $post = new Minpost();
        $this->assertEquals('minposts', $post->getTableName());
    }

    function testPrimaryKeyName()
    {
        $post = new Minpost();
        $this->assertEquals('minpost_id', $post->getPrimaryKey());
    }

    function testCreate()
    {
        $post = new Minpost();
        $post->title = 'Test Minpost';
        $post->body = 'Contents of Minpost.';
        $post->save();
        $this->assertTrue($post->minpost_id > 0);
    }

    function testFindByTitle()
    {
        $post = new Minpost();
        $theMinposts = $post->find('title', 'My Title');

        $this->assertEquals(1, count($theMinposts));
    }

    function testFind()
    {
        $post = new Minpost();
        $theMinposts = $post->find('minpost_id', 1);

        $this->assertEquals(1, count($theMinposts));
        $theMinpost = array_shift($theMinposts);

        $this->assertEquals('My Title', $theMinpost->title);
        $this->assertEquals('My Body.', $theMinpost->body);
    }

    function testFindArray()
    {
        $post = new Minpost();
        $theMinposts = $post->find(array('minpost_id' => 1));

        $this->assertEquals(1, count($theMinposts));
        $theMinpost = array_shift($theMinposts);

        $this->assertEquals('My Title', $theMinpost->title);
        $this->assertEquals('My Body.', $theMinpost->body);
    }

    function testFindOne()
    {
        $post = new Minpost();
        $theMinpost = $post->findOne('minpost_id', 1);

        $this->assertEquals('My Title', $theMinpost->title);
        $this->assertEquals('My Body.', $theMinpost->body);
    }

    function testFindConstructor()
    {
        $post = new Minpost(1);

        $this->assertEquals('My Title', $post->title);
        $this->assertEquals('My Body.', $post->body);
    }

}
