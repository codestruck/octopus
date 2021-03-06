<?php

db_error_reporting(DB_PRINT_ERRORS);

/**
 * @group Model
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class ModelTriggerTest extends Octopus_DB_TestCase
{

    function __construct()
    {
        parent::__construct('model/trigger-data.xml');
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

    function testVirtualOnAccess()
    {
        $item = new Trigger(1);
        $this->assertEquals('onAccess', $item->v);
    }


}

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
function trigger_onCreate(Octopus_Model $model, Octopus_Model_Field $field) {
    return 'created';
}

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
function trigger_onSave(Octopus_Model $model, Octopus_Model_Field $field) {
    return 'everytime';
}

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
function trigger_onUpdate(Octopus_Model $model, Octopus_Model_Field $field) {
    return 'updateonly';
}

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
function trigger_onEmpty(Octopus_Model $model, Octopus_Model_Field $field) {
    return 'onlywhenempty';
}

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
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
        ),
        'v' => array(
            'type' => 'virtual',
            'onAccess' => 'onAccess',
        ),
    );

    public function trigger_member($model, $field) {
        return 'fromMemberClass';
    }

    public function onAccess($model, $field) {
        return 'onAccess';
    }
}
