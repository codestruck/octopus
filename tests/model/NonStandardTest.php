<?php

/**
 * @group Model
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class ModelNonStandardTest extends PHPUnit_Framework_TestCase
{

    function testDifferentTableName()
    {
        $item = new Different();
        $this->assertEquals('nonstandard', $item->getTableName());
        $this->assertEquals('different_id', $item->getPrimaryKey());
    }

    function testDifferentPrimaryKey()
    {
        $item = new Differentb();
        $this->assertEquals('differentbs', $item->getTableName());
        $this->assertEquals('id', $item->getPrimaryKey());
    }

    function testDifferentPrimaryKeyAndTable()
    {
        $item = new Differentc();
        $this->assertEquals('randomtable', $item->getTableName());
        $this->assertEquals('foobar', $item->getPrimaryKey());
    }

    function testUsingNonStandard()
    {
        $item = new Differentc();
        $item->title = 'My Title';
        $item->save();
        $this->assertEquals(1, $item->foobar);

        $other = new Differentc(1);
        $this->assertEquals('My Title', $other->title);
    }

    function testDifferentDisplayField()
    {
        $item = new Differentd();
        $this->assertEquals('kazi', $item->getDisplayField()->getFieldName());
    }

    function testDifferentDisplayValue()
    {
        $item = new Differentd();
        $item->kazi = 'My Name';
        $item->save();

        $this->assertEquals('My Name', $item->getDisplayValue());
        $other = new Differentd(1);
        $this->assertEquals('My Name', $other->getDisplayValue());
    }

    function testFindDifferentDisplayValue()
    {
        return $this->markTestSkipped("This is not good behavior, I think.");

        $item = new Differentd();
        $item->kazi = 'My Other Name';
        $item->save();

        $other = new Differentd('My Other Name');
        $this->assertEquals(2, $other->differentd_id);
    }

    function testRowNotFound()
    {
        $item = new Different(5);
        $this->assertEquals(5, $item->different_id);
    }

    /**
     * @expectedException Octopus_Model_Exception
     */
    function testUnknownPropertyGet()
    {
        $item = new Different();
        $try = $item->nonexisting;

    }

    /**
     * @expectedException Octopus_Model_Exception
     */
    function testUnknownPropertySet()
    {
        $item = new Different();
        $item->nonexisting = 'foo';

    }

    function testPluralize() {

        $cat = new Category();
        $cat->name = 'foo';
        $cat->save();

        $cat = new Category(1);
        $this->assertEquals('foo', $cat->name);

    }

    function testNoDisplayField() {

        return $this->markTestSkipped("No display field should return the ID.");

        $lack = new Lack();
        $lack->notitle = 'foo';

        $this->assertEquals('foo', $lack->getDisplayValue());
        $this->assertEquals('foo', (string)$lack);

    }

    function testNoDisplayFieldNoText() {

        $item = new Notext();
        $item->number = 42;
        $item->save();

        $this->assertEquals(1, $item->getDisplayValue());
        $this->assertEquals(1, (string)$item);

    }

    function testNoDisplayFieldNoTextNotSaved() {

        $item = new Notext();
        $item->number = 42;

        $this->assertEquals('', $item->getDisplayValue());
        $this->assertEquals('', (string)$item);

    }

}


/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Different extends Octopus_Model {
    protected $table = 'nonstandard';
    protected $fields = array(
        'title' => array(),
        'created',
        'updated',
    );
}

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Differentb extends Octopus_Model {
    protected $primaryKey = 'id';
    protected $fields = array(
        'title' => array(),
        'created',
        'updated',
    );
}

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Differentc extends Octopus_Model {
    protected $primaryKey = 'foobar';
    protected $table = 'randomtable';
    protected $fields = array(
        'title' => array(),
        'created',
        'updated',
    );
}

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Differentd extends Octopus_Model {
    protected $displayField = 'kazi';
    protected $fields = array(
        'kazi' => array(),
        'created',
        'updated',
    );
}

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Category extends Octopus_Model {
    protected $fields = array(
        'name' => array(),
    );
}
