<?php

require_once('PHPUnit/Extensions/Database/TestCase.php');

SG::loadClass('SG_DB');
SG::loadClass('SG_Model');

db_error_reporting(DB_PRINT_ERRORS);

class Post extends SG_Model {
    public $title;
    public $body;
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
        return $this->createFlatXMLDataSet(dirname(__FILE__) . '/model-data.xml');
    }

    function __construct()
    {
        $db =& SG_DB::singleton();
        $db->query('DROP TABLE IF EXISTS posts');

        $sql = "CREATE TABLE posts (
                `post_id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `title` varchar ( 255 ) NOT NULL,
                `body` text NOT NULL
                )
                ";

        $db->query($sql);

    }

    function __destruct()
    {
        $db =& SG_DB::singleton();
        $db->query('DROP TABLE IF EXISTS posts');
    }

    function testTableName()
    {
        $post = new Post();
        $this->assertEquals('posts', $post->getTableName());
    }

    function testPrimaryKeyName()
    {
        $post = new Post();
        $this->assertEquals('post_id', $post->getPrimaryKey());
    }

    function testCreate()
    {
        $post = new Post();
        $post->title = 'Test Post';
        $post->body = 'Contents of Post.';
        $post->save();
        $this->assertTrue($post->post_id > 0);
    }

    function testFindByTitle()
    {
        $post = new Post();
        $thePosts = $post->find('title', 'My Title');

        $this->assertEquals(1, count($thePosts));
    }

    function testFind()
    {
        $post = new Post();
        $thePosts = $post->find('post_id', 1);

        $this->assertEquals(1, count($thePosts));
        $thePost = array_shift($thePosts);

        $this->assertEquals('My Title', $thePost->title);
        $this->assertEquals('My Body.', $thePost->body);
    }

    function testFindArray()
    {
        $post = new Post();
        $thePosts = $post->find(array('post_id' => 1));

        $this->assertEquals(1, count($thePosts));
        $thePost = array_shift($thePosts);

        $this->assertEquals('My Title', $thePost->title);
        $this->assertEquals('My Body.', $thePost->body);
    }

    function testFindOne()
    {
        $post = new Post();
        $thePost = $post->findOne('post_id', 1);

        $this->assertEquals('My Title', $thePost->title);
        $this->assertEquals('My Body.', $thePost->body);
    }

    function testFindConstructor()
    {
        $post = new Post(1);

        $this->assertEquals('My Title', $post->title);
        $this->assertEquals('My Body.', $post->body);
    }

}
