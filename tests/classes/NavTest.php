p<?php

SG::loadClass('SG_Nav');

/**
 * @group nav
 */
class SG_Nav_Test extends PHPUnit_Framework_TestCase {

    function testShutTheHellUpPHPUnit() {
        $this->assertTrue(true);
    }


    function xtestAddAndFindSimpleItem() {

        $nav = new SG_Nav();

        $nav->add('foo', 'Foo!');

        $item = $nav->find('/foo');
        $this->assertTrue($item == true, "Item not found!");

        $this->assertEquals('foo', $item->getPath());
        $this->assertEquals('Foo!', $item->getText());

    }

    function xtestAddSimpleItemDeep() {

        $nav = new SG_Nav();

        $levels = array('some', 'item', 'really', 'deep');
        $nav->add('/' . implode('/', $levels));

        $item = $nav->find('some');
        $this->assertTrue($item !== false, 'some not found');

        $item = $item->find('item');
        $this->assertTrue($item !== false, 'item not found');

        $item = $item->find('really');
        $this->assertTrue($item !== false, 'really not found');

        $item = $item->find('deep');
        $this->assertTrue($item !== false, 'deep not found');

    }

    function testAddAndFindChild() {

        $nav = new SG_Nav();

        $nav
            ->add('parent', 'Parent')
                ->add('child', 'Child');

        $parent = $nav->find('parent');
        $this->assertEquals(1, count($parent->getChildren()), 'parent should have a single child');

        $child = $nav->find('/parent/child');
        $this->assertTrue($child !== false, 'Child not found via /parent/child.');
        $this->assertEquals('child', $child->getPath(), '$childs path is wrong');
        $this->assertEquals('parent/child', $child->getFullPath(), 'childs full path is wrong');

        $child = $parent->find('child');
        $this->assertTrue($child !== false, 'Child not found via $parent->find()');
    }

    function xtestAddRegexItem() {

        $nav = new SG_Nav();

        $nav->add(array(
            'regex' => '/(?P<id>\d+)(?:-(?P<slug>.+))/',
            'file' => '{slug}.php'
        ));


        $item = $nav->find('/42-some-product');
        $this->assertTrue($item, 'matching item not found');

        $item = $nav->find('/some-product');
        $this->assertFalse($item, 'matching item found when it shouldnt be');

    }

    function notestAddArray() {


        $nav = new SG_Nav();

        $nav->add(array(

            'home' => array(
                'title' => 'Home',
                'children' => array(
                    'privacy' => array('title' => 'Privacy')
                )
            ),
            'products' => array(
                'title' => 'Our Products'
            )

        ));

        $home = $nav->find('home');
        $this->assertEquals('Home', $home->getTitle());

        $privacy = $nav->find('home/privacy');
        $this->assertEquals('Privacy', $privacy->getTitle());

        $products = $nav->find('products');
        $this->assertEquals('Our Products', $products->getTitle());

    }

    function xtestMap() {

        $nav = new SG_Nav();
        $nav->add('home', 'Home');

        $nav->map('/', 'home');

        $home = $nav->find('/');
        $this->assertTrue($home !== false, 'home not found!');
        $this->assertEquals('Home', $home->getText());
    }

}



?>
