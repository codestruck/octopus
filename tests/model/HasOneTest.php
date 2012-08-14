<?php

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class HasOneTest extends Octopus_App_TestCase {

    function setUp() {

        parent::setUp();

        Octopus::loadClass('Octopus_DB_Schema_Model');
        Octopus_DB_Schema_Model::makeTable('HasOnePerson');
        Octopus_DB_Schema_Model::makeTable('HasOneCategory');
        Octopus_DB_Schema_Model::makeTable(new Incorrect_Casing());

    }

    function testInitializeFromArray() {

        $category = new HasOneCategory();
        $category->name = __METHOD__;
        $category->save();

        $person = new HasOnePerson(array(
            'category' => $category
        ));
        $this->assertTrue(!!$person->category, "category initialized from array passed to ctor");
        $this->assertEquals($category->id, $person->category->id);
        $person->save();

        $person = new HasOnePerson($person->id);
        $this->assertEquals($category->id, $person->category->id);

    }

    function testUnsetLazyLoadedHasOne() {

        $category = new HasOneCategory();
        $category->name = __METHOD__;
        $category->save();

        $person = new HasOnePerson();
        $person->category = $category;
        $person->save();

        $person = new HasOnePerson($person->id);
        $person->category = null;

        $this->assertSame(null, $person->category);

        $person->save();
        $this->assertSame(null, $person->category);

    }

    function testHasOneNullByDefault() {

        $person = new HasOnePerson();
        $this->assertNull($person->category, 'category is null by default');

    }

    function testSaveHasOne() {

        $cat = new HasOneCategory();
        $cat->name = __METHOD__;

        $person = new HasOnePerson();
        $person->category = $cat;

        $this->assertTrue(!!$person->save(), "save() succeeds");

        $person = new HasOnePerson($person->id);
        $this->assertEquals($cat->id, $person->category->id);

        $person->category = null;
        $this->assertNull($person->category);
        $this->assertTrue(!!$person->save(), "save() succeeds after nulling");

        $person = new HasOnePerson($person->id);
        $this->assertFalse(!!$person->category, "category is unset");
    }

    function testSetHasOneToID() {

        $cat = new HasOneCategory();
        $cat->name = __METHOD__;
        $cat->save();

        $person = new HasOnePerson();
        $person->category = $cat->id;

        $this->assertTrue(!!$person->category, 'category set to something via id');
        $this->assertEquals($cat->id, $person->category->id);
        $this->assertEquals($cat->name, $person->category->name);

    }

    function testCascadeSaveByDefault() {

        $cat = new HasOneCategory();
        $cat->name = __METHOD__;

        $person = new HasOnePerson();
        $person->category = $cat;

        $person->save();
        $this->assertTrue(!!$cat->id, "category saved when person saved");


    }

    /**
     * @expectedException Octopus_Model_Exception
     */
    function testDisableCascadeAndSaveUnsavedThrowsException() {

        $cat = new HasOneCategory();
        $cat->name = __METHOD__;

        $person = new HasOnePerson();
        $person->no_cascade_save_category = $cat;

        $person->save();
        $this->assertFalse(!!$cat->id, 'category not saved');
    }

    function testCascadeDeleteDisabledByDefault() {

        $cat = new HasOneCategory();
        $cat->name = __METHOD__;

        $person = new HasOnePerson();
        $person->category = $cat;


        $person->save();
        $personID = $person->id;
        $person->delete();

        $person =HasOnePerson::get($personID);
        $this->assertFalse(!!$person, 'person deleted after delete() call');

        $cat = HasOneCategory::get($cat->id);
        $this->assertTrue(!!$cat, 'category still exists after deleting person');

    }

    function testCascadeDelete() {

        $this->markTestSkipped("TODO: implement cascading delete");

        $cat = new HasOneCategory();
        $cat->name = __METHOD__;
        $cat->save();

        $person = new HasOnePerson();
        $person->cascade_delete_category = $cat;
        $person->save();

        $personID = $person->id;
        $catID = $cat->id;

        $person->delete();

        $this->assertFalse(!!HasOnePerson::get($personID), 'person deleted after delete() call');
        $this->assertFalse(!!HasOneCategory::get($catID), 'category deleted after deleting person');

    }

    function testSpecificModelName() {

        $person = new HasOnePerson();
        $ic = new Incorrect_Casing();
        $ic->name = 'Tandy';

        $person->camel_case_model = $ic;
        $person->save();

        $ic = Incorrect_Casing::get(1);
        $this->assertEquals('Tandy', $ic->name);

    }

}

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class HasOneCategory extends Octopus_Model {
    protected $fields = array('name');
}

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Incorrect_Casing extends Octopus_Model {
    protected $fields = array('name');
}

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class HasOnePerson extends Octopus_Model {
    protected $fields = array(
        'name',
        'category' => array(
            'type' => 'hasOne',
            'model' => 'HasOneCategory'
        ),
        'no_cascade_save_category' => array(
            'type' => 'hasOne',
            'model' => 'HasOneCategory',
            'cascade' => false
        ),
        'cascade_delete_category' => array(
            'type' => 'hasOne',
            'model' => 'HasOneCategory',
            'cascade' => 'delete'
        ),
        'skip_save_category' => array(
            'type' => 'hasOne',
            'model' => 'HasOneCategory',
            'skipSave' => true
        ),
        'camel_case_model' => array(
            'type' => 'hasOne',
            'model' => 'Incorrect_Casing',
        ),
    );
}
