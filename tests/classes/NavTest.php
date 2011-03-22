<?php

    SG::loadClass('SG_Nav');

    /**
     * @group nav
     */
    class SG_Nav_Test extends PHPUnit_Framework_TestCase {

        function testAddAndFindSimpleItem() {

            $nav = new SG_Nav();

            $nav->add('/foo', 'Foo!');

            $item = $nav->find('/foo');
            $this->assertTrue($item, "Item not found!");

            $this->assertEquals('/foo', $item->path);
            $this->assertEquals('Foo!', $item->text);

        }

        function testAddAndFindChild() {

            $nav = new SG_Nav();

            $nav
                ->add('/parent', 'Parent')
                    ->add('/child', 'Child');

            $parent = $nav->find('/');
            $this->assertEquals(1, count($parent->children));

            $child = $nav->find('/parent/child');
            $this->assertTrue($child, 'Child not found via /parent/child.');

            $child = $parent->find('child');
            $this->assertTrue($child, 'Child not found via $parent->find()');
        }

        function testAddRegexItem() {

            $nav = new SG_Nav();

            $nav->add(array(
                'pattern' => '/(?P<id>\d+)(?:-(?P<slug>.+))/'
            ));


            $item = $nav->find('/42-some-product');
            $this->assertTrue($item, 'matching item not found');

            $item = $nav->find('/some-product');
            $this->assertFalse($item, 'matching item found when it shouldnt be');

        }


    }



?>
