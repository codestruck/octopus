p<?php

SG::loadClass('SG_Nav');

/**
 * @group nav
 */
class SG_Nav_Test extends PHPUnit_Framework_TestCase {

    function testAddAndFindSimpleItem() {

        $nav = new SG_Nav();

        $nav->add('foo', 'Foo!');

        $item = $nav->find('/foo');
        $this->assertTrue($item == true, "Item not found!");

        $this->assertEquals('foo', $item->getPath());
        $this->assertEquals('Foo!', $item->getText());

    }

    function testAddSimpleItemDeep() {

        $nav = new SG_Nav();

        $levels = array('some', 'item', 'really', 'deep');
        $nav->add('/' . implode('/', $levels));

        $item = null;
        $path = '';
        foreach($levels as $l) {

            $path .= '/' . $l;

            $item = $nav->find($path);
            $this->assertTrue($item !== false, 'Item not found at path: ' . $path);

            if ($item) {
                $child = $item->find($l);
                $this->assertTrue($child !== false, 'Child not found under ' . $item->getPath());
            }

        }

    }

    function testAddAndFindChild() {

        $nav = new SG_Nav();

        $nav
            ->add('/parent', 'Parent')
                ->add('/child', 'Child');

        $parent = $nav->find('/');
        $this->assertEquals(1, count($parent->getChildren()));

        $child = $nav->find('/parent/child');
        $this->assertTrue($child, 'Child not found via /parent/child.');

        $child = $parent->find('child');
        $this->assertTrue($child, 'Child not found via $parent->find()');
    }

    function testAddRegexItem() {

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

    function testAddArray() {


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

    function testMap() {

        $nav = new SG_Nav();
        $nav->add('home', 'Home');

        $nav->map('/', 'home');

        $home = $nav->find('/');
        $this->assertTrue($home !== false, 'home not found!');
        $this->assertEquals('Home', $home->getText());
    }

}



?>
