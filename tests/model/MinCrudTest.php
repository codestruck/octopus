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
        //'author' => array(
        //    'type' => 'has_one'
        //),
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
class ModelMinCrudLoadTest extends PHPUnit_Extensions_Database_TestCase
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
        $post->title = 'Test post';
        $post->body = 'Contents of post.';
        $post->save();
        $this->assertTrue($post->minpost_id > 0);
    }

    function testIsSaved()
    {
        $post = new Minpost();
        $post->title = 'Test post';
        $post->body = 'Contents of post.';
        $this->assertFalse($post->isSaved());
        $post->save();
        $this->assertTrue($post->isSaved());
    }

    function testDelete()
    {
        $s = new SG_DB_Select();
        $s->table('minposts');
        $s->where('minpost_id = ?', 1);
        $query = $s->query();
        $this->assertEquals(1, $query->numRows());

        $post = new Minpost(1);
        $post->delete();

        $s = new SG_DB_Select();
        $s->table('minposts');
        $s->where('minpost_id = ?', 1);
        $query = $s->query();
        $this->assertEquals(0, $query->numRows());
    }

    function testUpdate()
    {
        $post = new Minpost(1);
        $this->assertEquals('My Title', $post->title);
        $this->assertEquals('My Body.', $post->body);

        $post_id = $post->minpost_id;
        $count = table_count('minposts');

        $post->title = 'Test post';
        $post->body = 'Contents of post.';
        $post->save();

        $this->assertEquals($count, table_count('minposts'));
        $this->assertEquals($post_id, $post->minpost_id);
    }

    function testAttribute()
    {
        $post = new Minpost(1);
        $this->assertEquals(1, $post->active);
    }

    function testCreateTimestamps()
    {
        $post = new Minpost();
        $post->title = 'Test post';
        $post->body = 'Contents of post.';
        $post->save();

        $now = time();
        $fiveminsago = $now - 300;
        $created = strtotime($post->created);

        $this->assertTrue($created > $fiveminsago);
        $this->assertTrue($created <= $now);

    }

    function testSlugCreation()
    {
        $post = new Minpost();
        $post->title = 'Test post';
        $post->body = 'Contents of post.';
        $post->save();
        $this->assertEquals('test-post', $post->slug);

    }

    function testSlugUniqueness()
    {
        $post = new Minpost();
        $post->title = 'Duplicate Title';
        $post->body = 'Contents of post.';
        $post->save();
        $this->assertEquals('duplicate-title', $post->slug);

        $post = new Minpost();
        $post->title = 'Duplicate Title';
        $post->body = 'Contents of post.';
        $post->save();
        $this->assertEquals('duplicate-title2', $post->slug);
    }

    function testSlugNotModified()
    {
        $post = new Minpost(1);
        $post->title = 'New Title Should Not Affect Slug';
        $post->save();

        $this->assertEquals('my-title', $post->slug);
        $this->assertEquals(1, $post->minpost_id);
    }

    function testValidate()
    {
        $post = new Minpost();
        $post->title = 'Has a Title';
        $post->body = 'And a body';
        $this->assertTrue($post->validate());
    }

    function testValidateMissingTitle()
    {
        $post = new Minpost();
        $post->body = 'Just a body';
        $this->assertFalse($post->validate());
    }

       function testValidateSave()
    {
        $count = table_count('minposts');

        $post = new Minpost();
        $post->title = 'Has a Title';
        $post->body = 'And a body';
        $this->assertTrue($post->save());
        $this->assertEquals($count + 1, table_count('minposts'));
    }

    function testValidateSaveMissingTitle()
    {
        $count = table_count('minposts');

        $post = new Minpost();
        $post->body = 'Just a body';
        $this->assertFalse($post->save());
        $this->assertEquals($count, table_count('minposts'));
    }

    function testGetErrors()
    {
        $post = new Minpost();
        $post->title = 'Has a Title';
        $post->body = 'And a body';
        $this->assertEquals(0, count($post->getErrors()));
    }

    function testGetErrorsMissingTitle()
    {
        $post = new Minpost();
        $post->body = 'Just a body';

        $this->assertFalse($post->save());
        $errors = $this->getErrors();
        $error = array_pop($errors);
        $this->assertEquals(1, count($errors));

        $this->assertEquals('title', $error['field']);
        $this->assertEquals('Missing Title', $error['message'], 'What should the message be?');
    }

}
