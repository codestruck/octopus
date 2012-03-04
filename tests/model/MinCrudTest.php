<?php

db_error_reporting(DB_PRINT_ERRORS);

class Minpost extends Octopus_Model {
    protected $fields = array(
        'title' => array(
            'required' => true,
        ),
        'slug' => array(
            'type' => 'slug', // implies hidden input
            'onEmpty' => 'to_unique_slug',
        ),
        'body' => array(
            'type' => 'html',
            'sanitize' => 'mce_cleanup',
        ),
        'active' => array(
            'type' => 'boolean',
        ),
        'display_order' => array(
            'type' => 'numeric',
        ),
        'created',
        'updated',
        'cost' => array(
            'type' => 'numeric',
            'decimal_places' => 2,
            'precision' => 6,
        ),
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
        $this->assertTrue(!!$post->save());
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

    function testPostLoad()
    {
        $post = new Minpost(1);
        $this->assertEquals(1, $post->minpost_id);
        $this->assertEquals('My Title', $post->title);
        $this->assertEquals('My Body.', $post->body);
    }

    function testPostUpdate()
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

    function testJustUpdate()
    {
        $post = new Minpost(1);
        $post->title = 'Just Update';
        $post->save();

        $s = new Octopus_DB_Select();
        $s->table('minposts');
        $s->where('minpost_id = ?', 1);
        $result = $s->fetchRow();

        $this->assertEquals('Just Update', $result['title']);
        $this->assertEquals('My Body.', $result['body']);
    }

    function testPostUpdateBlank()
    {
        $post = new Minpost(1);
        $this->assertEquals('My Title', $post->title);
        $this->assertEquals('My Body.', $post->body);

        $post->body = '';
        $post->save();

        $post = new Minpost(1);
        $this->assertEquals('', $post->body);
    }

    function testAttribute()
    {
        $post = new Minpost(1);
        $this->assertEquals(1, $post->active);
    }

    function testCreateTimestampsOnCreate()
    {
        $post = new Minpost();
        $this->assertFalse($post->exists());
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

    /*
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
        $this->assertEquals('is required', $error['message']);
    }
    */

    function testToString()
    {
        $post = new Minpost(1);
        $this->assertEquals('My Title', (string)$post);
    }

    function testDeleteLazy()
    {
        $post = new Minpost(1);
        $post->delete();
        $this->assertNull($post->id, 'id reset after delete');
        $this->assertFalse($post->exists());

        $post = new Minpost(1);
        $this->assertEquals(1, $post->minpost_id, 'no existence check run when id passed to constructor');
        $this->assertFalse($post->exists(), 'existence check fails');

        $post = new Minpost(1);
        $post->save();
        $this->assertEquals(3, $post->id, 'id updated when saved after delete');
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

    function testResultSetArrayAccess() {
        $all = Minpost::all();
        $this->assertEquals('My Title', $all[0]->title);
        $this->assertEquals('My Other Title', $all[1]->title);
        $this->assertEquals(null, $all[2]);
    }

    function testResultSetArrayAccessExists() {
        $all = Minpost::all();
        $this->assertEquals(true, isset($all[0]));
        $this->assertEquals(true, isset($all[1]));
        $this->assertEquals(false, isset($all[2]));
    }

    /**
     * @expectedException Octopus_Model_Exception
     */
    function testResultSetArrayAccessSetFail() {
        $all = Minpost::all();
        $all[0] = 'foo';
    }

    /**
     * @expectedException Octopus_Model_Exception
     */
    function testResultSetArrayAccessUnSetFail() {
        $all = Minpost::all();
        unset($all[0]);
    }

    function testModelArrayAccess() {
        $post = new Minpost(1);
        $this->assertEquals('My Title', $post['title']);
    }

    function testModelArrayAccessSet() {
        $post = new Minpost(1);
        $post['title'] = 'Changed Title';
        $post->save();

        $post = new Minpost(1);
        $this->assertEquals('Changed Title', $post['title']);
    }

    function testModelArrayAccessUnSet() {
        $post = new Minpost(1);
        unset($post['body']);
        $post->save();

        $post = new Minpost(1);
        $this->assertEquals('', $post['body']);
    }

    function dontTestModelIsSet() {

        $post = new Minpost();
        $this->assertTrue(isset($post->title), 'title should be set');
        $this->assertFalse(isset($post->invalidField), 'invalid field should not be set');

    }

    function testSettingNumeric() {

        $post = new Minpost();
        $post->title = 'numbers';
        $post->display_order = '$9,901';
        $post->save();

        $post = new Minpost(3);
        $this->assertEquals(9901, $post->display_order);

    }

    function testSettingNumericDecimal() {

        $post = new Minpost();
        $post->title = 'numbers';
        $post->display_order = '$9,901.11';
        $post->save();

        $this->assertEquals(3, $post->minpost_id);

        $post = new Minpost(3);
        $this->assertEquals(9901, $post->display_order);

    }

    function testUnsetLazy() {

        $post = new Minpost(1);
        unset($post->body);
        $this->assertEquals('', $post->body);
        $post->save();

        $post = new Minpost(1);
        $this->assertEquals('', $post->body);

    }

    function testUnsetLoaded() {

        $post = new Minpost(1);
        $this->assertEquals('My Body.', $post->body);
        unset($post->body);
        $this->assertEquals('', $post->body);

    }

    function testIssetExistingLazy() {
        $post = new Minpost(1);
        $this->assertTrue(isset($post->title));
        $this->assertFalse(isset($post->nonexistant));
    }

    function testIssetExistingLoaded() {
        $post = new Minpost(1);
        $this->assertEquals('My Title', $post->title);
        $this->assertTrue(isset($post->title));
        $this->assertFalse(isset($post->nonexistant));
    }

    function testIssetNewLazy() {
        $post = new Minpost();
        $this->assertTrue(isset($post->title));
        $this->assertFalse(isset($post->nonexistant));
    }

    function testIteratorExistingValue() {

        $post = new Minpost(1);

        $i = 0;
        foreach ($post as $key => $item) {
            switch ($i) {
                case 0:
                    $this->assertEquals(1, $item);
                break;
                case 1:
                    $this->assertEquals('My Title', $item);
                break;
                case 2:
                    $this->assertEquals('my-title', $item);
                break;
                case 3:
                    $this->assertEquals('My Body.', $item);
                break;
                case 4:
                    $this->assertEquals('1', $item);
                break;
                case 5:
                    $this->assertEquals(0, $item);
                break;
                case 6:
                    $this->assertEquals('2000-03-20 04:20:11', $item);
                break;
                case 7:
                    $this->assertEquals('2001-03-20 04:20:11', $item);
                break;

            }

            $i++;
        }

        $this->assertEquals(9, $i);

    }

    function testIteratorExistingKeyValue() {

        $post = new Minpost(1);

        $i = 0;
        foreach ($post as $key => $item) {
            switch ($i) {
                case 0:
                    $this->assertEquals(1, $item);
                    $this->assertEquals('minpost_id', $key);
                break;
                case 1:
                    $this->assertEquals('My Title', $item);
                    $this->assertEquals('title', $key);
                break;
                case 2:
                    $this->assertEquals('my-title', $item);
                    $this->assertEquals('slug', $key);
                break;
                case 3:
                    $this->assertEquals('My Body.', $item);
                    $this->assertEquals('body', $key);
                break;
                case 4:
                    $this->assertEquals('1', $item);
                    $this->assertEquals('active', $key);
                break;
                case 5:
                    $this->assertEquals(0, $item);
                    $this->assertEquals('display_order', $key);
                break;
                case 6:
                    $this->assertEquals('2000-03-20 04:20:11', $item);
                    $this->assertEquals('created', $key);
                break;
                case 7:
                    $this->assertEquals('2001-03-20 04:20:11', $item);
                    $this->assertEquals('updated', $key);
                break;

            }

            $i++;
        }

        $this->assertEquals(9, $i);

    }

    function testCount() {
        $post = new Minpost(1);
        $this->assertEquals(9, count($post));

        $post = new Minpost();
        $this->assertEquals(9, count($post));

    }

    function testDecimalNew() {
        $post = new Minpost();
        $post->title = 'foo';
        $post->cost = 1;

        $post->save();
        $post_id = $post->minpost_id;

        $post = new Minpost($post_id);

        $this->assertEquals(1.00, $post->cost);
    }

    function testDecimalReuse() {
        $post = new Minpost();
        $post->title = 'foo';
        $post->cost = 1;

        $post->save();

        $this->assertEquals(1.00, $post->cost);
    }

    function testDecimalOverflowNew() {
        $post = new Minpost();
        $post->title = 'foo';
        $post->cost = 12345678.90;

        $post->save();
        $post_id = $post->minpost_id;

        $post = new Minpost($post_id);

        $this->assertEquals(9999.99, $post->cost);
    }

    function testDecimalOverflowReuse() {
        $post = new Minpost();
        $post->title = 'foo';
        $post->cost = 12345678.90;

        $post->save();

        $this->assertEquals(9999.99, $post->cost);
    }

    function testDecimalOverflowMaxNew() {
        $post = new Minpost();
        $post->title = 'foo';
        $post->cost = 99999999.99;

        $post->save();
        $post_id = $post->minpost_id;

        $post = new Minpost($post_id);

        $this->assertEquals(9999.99, $post->cost);
    }

    function testDecimalOverflowMaxReuse() {
        $post = new Minpost();
        $post->title = 'foo';
        $post->cost = 99999999.99;

        $post->save();

        $this->assertEquals(9999.99, $post->cost);
    }

    function testLazyLoadDoesntOverwriteData() {
disable_dump_r();
        $post = new Minpost();
        $post->title = "foo";
        $post->display_order = 5;
        $this->assertTrue(!!$post->save(), 'save succeeds');

        $post = new Minpost($post->id);
        $this->assertEquals("foo", $post->title);
        $this->assertEquals(5, $post->display_order);
enable_dump_r();
        $post = new Minpost($post->id);
        $post->title = "bar";

        $this->assertEquals(5, $post->display_order);
        $this->assertEquals("bar", $post->title);

    }

}
