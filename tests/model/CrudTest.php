<?php
require_once 'PHPUnit/Framework/TestCase.php';

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
class ModelCrudTest extends PHPUnit_Framework_TestCase
{

    function setUp()
    {
        $db =& SG_DB::singleton();
        $db->query('DROP TABLE posts');


        $sql = "CREATE TABLE posts (
                `post_id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `title` varchar ( 255 ) NOT NULL,
                `body` text NOT NULL
                )
                ";

        $db->query($sql);

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
        $this->assertEquals(1, $post->post_id);
    }

    function testFindByTitle()
    {
        $i = new SG_DB_Insert();
        $i->table('posts');
        $i->set('title', 'My Title');
        $i->set('body', 'My Body');
        $i->execute();

        $i = new SG_DB_Insert();
        $i->table('posts');
        $i->set('title', 'My Title');
        $i->set('body', 'My Other Body');
        $i->execute();

        $post = new Post();
        $thePosts = $post->find('title', 'My Title');

        $this->assertEquals(2, count($thePosts));
    }

    function testFind()
    {
        $i = new SG_DB_Insert();
        $i->table('posts');
        $i->set('title', 'My Title');
        $i->set('body', 'My Body');
        $i->execute();

        $post_id = $i->getId();

        $post = new Post();
        $thePosts = $post->find('post_id', $post_id);

        $this->assertEquals(1, count($thePosts));
        $thePost = array_shift($thePosts);

        $this->assertEquals('My Title', $thePost->title);
        $this->assertEquals('My Body', $thePost->body);
        $this->assertEquals($post_id, $thePost->post_id);

    }

    function testFindArray()
    {
        $i = new SG_DB_Insert();
        $i->table('posts');
        $i->set('title', 'My Title');
        $i->set('body', 'My Body');
        $i->execute();

        $post_id = $i->getId();

        $post = new Post();
        $thePosts = $post->find(array('post_id' => $post_id));

        $this->assertEquals(1, count($thePosts));
        $thePost = array_shift($thePosts);

        $this->assertEquals('My Title', $thePost->title);
        $this->assertEquals('My Body', $thePost->body);
        $this->assertEquals($post_id, $thePost->post_id);

    }

    function testFindOne()
    {
        $i = new SG_DB_Insert();
        $i->table('posts');
        $i->set('title', 'My Title');
        $i->set('body', 'My Body');
        $i->execute();

        $post_id = $i->getId();

        $post = new Post();
        $thePost = $post->findOne('post_id', $post_id);

        $this->assertEquals('My Title', $thePost->title);
        $this->assertEquals('My Body', $thePost->body);
        $this->assertEquals($post_id, $thePost->post_id);

    }

    function testFindConstructor()
    {
        $i = new SG_DB_Insert();
        $i->table('posts');
        $i->set('title', 'My Title');
        $i->set('body', 'My Body');
        $i->execute();

        $post_id = $i->getId();

        $post = new Post($post_id);

        $this->assertEquals('My Title', $post->title);
        $this->assertEquals('My Body', $post->body);

    }

}
