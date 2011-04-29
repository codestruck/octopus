<?php

SG::loadClass('SG_Html_Element');

class ElementTests extends PHPUnit_Framework_TestCase {

    function testSingleTagRender() {

        $e = new SG_Html_Element('img', array('src' => 'test.png', 'alt' => 'Alt Text'));
        $this->assertEquals(
            '<img src="test.png" alt="Alt Text" />',
            $e->render(true)
        );

    }

    function testAttributes() {

        $e = new SG_Html_Element('span');
        $e->class = 'testClass';
        $e->setAttribute('lang', 'en-us');
        $e->css('font-weight', 'bold');

        $this->assertEquals(
            "<span class=\"testClass\" style=\"font-weight: bold;\" lang=\"en-us\" />",
            $e->render(true)
        );

        unset($e->class);
        $this->assertEquals(
            "<span style=\"font-weight: bold;\" lang=\"en-us\" />",
            $e->render(true)
        );

        $e->removeAttribute('style');
        $this->assertEquals(
            "<span lang=\"en-us\" />",
            $e->render(true)
        );

        $e->setAttribute('lang', null);
        $this->assertEquals(
            "<span />",
            $e->render(true)
        );

        $e->data('id', 42);
        $this->assertEquals(
            "<span data-id=\"42\" />",
            $e->render(true)
        );

        $e->removeAttribute('data-id');
        $this->assertEquals('<span />', $e->render(true));

        $e->css(array('color' => 'red', 'font-weight' => 'bold'));
        $this->assertEquals('<span style="color: red; font-weight: bold;" />', $e->render(true));

        unset($e->style);
        $this->assertEquals('<span />', $e->render(true));

        $e->data(array('id' => 42, 'foo' => 'bar'));
        $this->assertEquals('<span data-foo="bar" data-id="42" />', $e->render(true));

        $e->removeAttribute('data-id');
        $this->assertEquals('<span data-foo="bar" />', $e->render(true));
    }

    function testAddAndRemoveClass() {

        $e = new SG_Html_Element('span');
        $e->addClass('foo');
        $this->assertEquals('<span class="foo" />', $e->render(true));

        $e->removeClass('foo');
        $this->assertEquals('<span />', $e->render(true));

        $e->addClass('foo bar');
        $this->assertEquals('<span class="foo bar" />', $e->render(true));

        $e->addClass(array('baz', 'bat'));
        $this->assertEquals('<span class="foo bar baz bat" />', $e->render(true));

        $e->removeClass(array('foo', 'baz'));
        $this->assertEquals('<span class="bar bat" />', $e->render(true));

        $e->removeClass('bar bat');
        $this->assertEquals('<span />', $e->render(true));

        $e->toggleClass('toggle');
        $this->assertEquals('<span class="toggle" />', $e->render(true));
        $e->addClass(' foo bar');
        $this->assertEquals('<span class="toggle foo bar" />', $e->render(true));

        $e->toggleClass('toggle');
        $this->assertEquals('<span class="foo bar" />', $e->render(true));

    }

    function testSetInnerText() {

        $e = new SG_Html_Element('span');
        $e->text('<escape> & <test>');
        $this->assertEquals(
            '<span>&lt;escape&gt; &amp; &lt;test&gt;</span>',
            $e->render(true)
        );

    }

    function testSetInnerHtml() {

        $e = new SG_Html_Element('span');
        $e->html('<b>bold!</b>');

        $this->assertEquals('<span><b>bold!</b></span>', $e->render(true));

    }

}

?>
