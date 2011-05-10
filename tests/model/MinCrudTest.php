<?php

Octopus::loadClass('Octopus_DB');
Octopus::loadClass('Octopus_Model');

db_error_reporting(DB_PRINT_ERRORS);

class Minpost extends Octopus_Model {
    protected $fields = array(
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
class ModelMinCrudLoadTest extends Octopus_DB_TestCase
{

    function __construct()
    {
        parent::__construct('model/crud-data.xml');
    }

    function createTables(&$db)
    {
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

    function dropTables(&$db)
    {
        $db =& Octopus_DB::singleton();
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
        $this->assertTrue($post->save());
        $this->assertTrue($post->minpost_id > 0);
    }

    function testDelete()
    {
        $s = new Octopus_DB_Select();
        $s->table('minposts');
        $s->where('minpost_id = ?', 1);
        $query = $s->query();
        $this->assertEquals(1, $query->numRows());

        $post = new Minpost(1);
        $post->delete();

        $s = new Octopus_DB_Select();
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

        $post->title = 'Test Update';
        $post->body = 'Contents of post.';
        $post->save();

        $this->assertEquals($count, table_count('minposts'));
        $this->assertEquals($post_id, $post->minpost_id);
        $this->assertEquals('Test Update', $post->title);
        $this->assertEquals('Contents of post.', $post->body);
    }

    function testAttribute()
    {
        $post = new Minpost(1);
        $this->assertEquals(1, $post->active);
    }

    function testCreateTimestampsOnCreate()
    {
        $post = new Minpost();
        $post->title = 'Create Timestamps';
        $post->body = 'Contents of post.';
        $post->save();

        $now = time();
        $fiveminsago = $now - 300;
        $created = strtotime($post->created);

        $this->assertTrue($created > $fiveminsago);
        $this->assertTrue($created <= $now);
    }

    function testCreateTimestampsOnUpdate()
    {
        $post = new Minpost(1);

        $this->assertEquals('2000-03-20 04:20:11', $post->created);

        $post->title = 'Create Timestamps';
        $post->body = 'Contents of post.';
        $post->save();

        $savedPost = new Minpost(1);
        $this->assertEquals('2000-03-20 04:20:11', $savedPost->created);

    }

    function testUpdateTimestampsOnCreate()
    {
        $post = new Minpost();
        $post->title = 'Create Timestamps';
        $post->body = 'Contents of post.';
        $post->save();

        $now = time();
        $fiveminsago = $now - 300;
        $updated = strtotime($post->updated);

        $this->assertTrue($updated > $fiveminsago);
        $this->assertTrue($updated <= $now);
    }

    function testUpdateTimestampsOnUpdate()
    {
        $post = new Minpost(1);

        $this->assertEquals('2001-03-20 04:20:11', $post->updated);

        $post->title = 'Create Timestamps';
        $post->body = 'Contents of post.';
        $post->save();

        $now = time();
        $fiveminsago = $now - 300;
        $created = strtotime($post->updated);

        $this->assertTrue($created > $fiveminsago);
        $this->assertTrue($created <= $now);
    }

    function testReuseModel()
    {
        $post = new Minpost(1);
        $this->assertEquals('My Title', $post->title);
        $this->assertEquals(1, $post->minpost_id);

        $post = new Minpost();
        $this->assertEquals(null, $post->title);
        $this->assertEquals(null, $post->minpost_id);
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
        $this->assertEquals('duplicate-title-2', $post->slug);
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

        $errors = $post->getErrors();
        $this->assertEquals(1, count($errors));

        $error = array_pop($errors);
        $this->assertEquals('title', $error['field']);
        $this->assertEquals('is Required', $error['message']);
    }

    function testSetBoolToZero()
    {
        $post = new Minpost(1);
        $this->assertEquals(1, $post->active);

        $post->active = 0;
        $post->save();

        $checkPost = new Minpost(1);
        $this->assertEquals(0, $checkPost->active);

    }

}
