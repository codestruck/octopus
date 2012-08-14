<?php

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class CompareTest extends Octopus_App_TestCase {

    function setUp() {

        parent::setUp();

        Octopus_DB_Schema_Model::makeTable('EqTestModel');
        Octopus_DB_Schema_Model::makeTable('EqTestModelSubclass');
        $db = Octopus_DB::singleton();

        $db->query('TRUNCATE TABLE eq_test_models');
        $db->query('TRUNCATE TABLE eq_test_model_subclasses');


    }

    function testEqWithNumber() {

        $m = new EqTestModel();


        $this->assertFalse($m->eq(0), 'Unsaved model not equal to 0');

        $m->save();
        $this->assertFalse($m->eq($m->id + 1), 'Model w/ id not equal to different id');

        $this->assertTrue($m->eq($m->id), 'Model w/ id equal to same id');
    }

    function testEqWithNull() {

        $m = new EqTestModel();
        $this->assertFalse($m->eq(null), 'not equal to null');

    }

    function testEqualToSelf() {

        $m = new EqTestModel();
        $this->assertTrue($m->eq($m), 'equal to self when unsaved');

        $m->save();
        $this->assertTrue($m->eq($m), 'equal to self when saved');

    }

    function testEqualToOtherOfSameClass() {

        $m = new EqTestModel();
        $m->save();

        $this->assertFalse($m->eq(new EqTestModel()), 'saved not equal to unsaved');

        $other = new EqTestModel($m->id);
        $this->assertTrue($m->eq($other), 'saved equal to another instance w/ same id');

    }

    function testNotEqualToSubclass() {

        $m = new EqTestModel();
        $other = new EqTestModelSubclass();

        $this->assertFalse($m->eq($other), 'not equal to unsaved subclass');
        $this->assertFalse($other->eq($m), 'not equal to unsaved superclass');

        $m->save();
        $this->assertFalse($m->eq($other), 'saved not equal to unsaved subclass');
        $this->assertFalse($other->eq($m), 'unsaved not equal to saved superclass');

        $other->save();

        $this->assertEquals($m->id, $other->id, 'sub- and super classes have same id');

        $this->assertFalse($m->eq($other), 'saved not equal to saved subclass');
        $this->assertFalse($other->eq($m), 'saved not equalto saved superclass');

    }

}

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class EqTestModel extends Octopus_Model {
    protected $fields = array('name');
}

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class EqTestModelSubclass extends EqTestModel {
    protected $fields = array('name');
}

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class EqTestOtherModel extends Octopus_Model {
    protected $fields = array('name');
}