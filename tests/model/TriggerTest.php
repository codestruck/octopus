<?php

Octopus::loadClass('Octopus_DB');
Octopus::loadClass('Octopus_Model');

db_error_reporting(DB_PRINT_ERRORS);

function trigger_onCreate(Octopus_Model $model, Octopus_Model_Field $field) {
    return 'created';
}
function trigger_onSave(Octopus_Model $model, Octopus_Model_Field $field) {
    return 'everytime';
}
function trigger_onUpdate(Octopus_Model $model, Octopus_Model_Field $field) {
    return 'updateonly';
}
function trigger_onEmpty(Octopus_Model $model, Octopus_Model_Field $field) {
    return 'onlywhenempty';
}

class Trigger extends Octopus_Model {
    protected $fields = array(
        'title' => array(),
        'a' => array(
            'onCreate' => 'trigger_onCreate',
        ),
        'b' => array(
            'onSave' => 'trigger_onSave',
        ),
        'c' => array(
            'onUpdate' => 'trigger_onUpdate',
        ),
        'd' => array(
            'onEmpty' => 'trigger_onEmpty',
        ),
        'e' => array(
            'onSave' => 'trigger_member',
        )
    );

    public function trigger_member($model, $field) {
        return 'fromMemberClass';
    }
}

/**
 * @group Model
 */
class ModelTriggerTest extends Octopus_DB_TestCase
{

    function __construct()
    {
        parent::__construct('model/trigger-data.xml');
    }

    function createTables(&$db)
    {
        $sql = "CREATE TABLE triggers (
                `trigger_id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `title` varchar ( 255 ) NOT NULL,
                `a` varchar ( 255 ) NOT NULL,
                `b` varchar ( 255 ) NOT NULL,
                `c` varchar ( 255 ) NOT NULL,
                `d` varchar ( 255 ) NOT NULL,
                `e` varchar ( 255 ) NOT NULL
                )
                ";

        $db->query($sql);
    }

    function dropTables(&$db)
    {
        $db->query('DROP TABLE IF EXISTS triggers');
    }

    function testOnSave_Create()
    {
        $item = new Trigger();
        $item->title = 'foo';
        $item->save();

        $this->assertEquals('everytime', $item->b);
    }

    function testOnSave_Update()
    {
        $item = new Trigger(1);
        $this->assertEquals('lorum', $item->b);
        $item->title = 'Changed';
        $item->save();
        $this->assertEquals('everytime', $item->b);
    }

    function testOnCreate_Create()
    {
        $item = new Trigger();
        $item->title = 'foo';
        $item->save();

        $this->assertEquals('created', $item->a);
    }

    function testOnCreate_Update()
    {
        $item = new Trigger(1);
        $this->assertEquals('lorum', $item->a);
        $item->title = 'Changed';
        $item->save();
        $this->assertEquals('lorum', $item->a, 'onCreate should not be called for updates');
    }

    function testOnUpdate_Create()
    {
        $item = new Trigger();
        $item->title = 'foo';
        $item->save();

        $this->assertEquals('', $item->c, 'onUpdated should not be called for creates');
    }

    function testOnUpdate_Update()
    {
        $item = new Trigger(1);
        $this->assertEquals('lorum', $item->c);
        $item->title = 'something else';
        $item->save();
        $this->assertEquals('updateonly', $item->c);
    }

    function testOnEmpty_Create()
    {
        $item = new Trigger();
        $item->d = 'something';
        $item->save();

        $this->assertEquals('something', $item->d, 'onEmpty should not overwrite values');
    }

    function testOnEmpty_Update_NotEmpty()
    {
        $item = new Trigger(1);
        $this->assertEquals('lorum', $item->d);
        $item->d = 'something else';
        $item->save();
        $this->assertEquals('something else', $item->d);
    }

    function testOnEmpty_Update_IsEmpty()
    {
        $item = new Trigger(1);
        $this->assertEquals('lorum', $item->d);
        $item->d = '';
        $item->save();
        $this->assertEquals('onlywhenempty', $item->d);
    }

    function testMemberTrigger()
    {
        $item = new Trigger();
        $item->title = 'Some Title';
        $item->save();
        $this->assertEquals('fromMemberClass', $item->e);
    }

}
