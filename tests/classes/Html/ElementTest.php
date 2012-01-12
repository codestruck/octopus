<?php

/**
 * @group Html
 */
class ElementTests extends Octopus_Html_TestCase {

	function testInsertBefore() {

		$parent = new Octopus_Html_Element('div');

		$child1 = new Octopus_Html_Element('span', array(), 'foo');
		$parent->append($child1);

		$child2 = new Octopus_Html_Element('span', array(), 'bar');
		$child2->insertBefore($child1);

		$this->assertHtmlEquals(
			<<<END
<div>
	<span>bar</span><span>foo</span>
</div>
END
			,
			$parent->render(true)
		);

	}

	function testInsertAfter() {

		$parent = new Octopus_Html_Element('div');

		$child1 = new Octopus_Html_Element('span', array(), 'foo');
		$parent->append($child1);

		$child2 = new Octopus_Html_Element('span', array(), 'bar');
		$child2->insertAfter($child1);

		$child3 = new Octopus_Html_Element('span', array(), 'baz');
		$child3->insertAfter($child1);

		$this->assertHtmlEquals(
			<<<END
<div>
	<span>foo</span><span>baz</span><span>bar</span>
</div>
END
			,
			$parent->render(true)
		);

	}


	function testIsTag() {

		$e = new Octopus_Html_Element('blockquote');
		$this->assertTrue($e->is('blockquote'));
		$this->assertFalse($e->is('div'));

	}

    function testSingleTagRender() {

        $e = new Octopus_Html_Element('img', array('src' => 'test.png', 'alt' => 'Alt Text'));
        $this->assertHtmlEquals(
            '<img src="test.png" alt="Alt Text" />',
            $e->render(true)
        );

    }

    function testAttrMethod() {

        $e = new Octopus_Html_Element('span', 'content');
        $e->attr('id', 'foo');
        $this->assertEquals('<span id="foo">content</span>', trim($e->render(true)));

        $this->assertEquals('foo', $e->attr('id'));

        $e->attr(array(
            'class' => 'testClass',
            'title' => 'test title'
        ));

        $this->assertEquals('<span id="foo" class="testClass" title="test title">content</span>', trim($e->render(true)));

    }

    function testAttributes() {

        $e = new Octopus_Html_Element('span');
        $e->class = 'testClass';
        $e->setAttribute('lang', 'en-us');
        $e->css('font-weight', 'bold');

        $this->assertHtmlEquals(
            "<span class=\"testClass\" style=\"font-weight: bold;\" lang=\"en-us\" />",
            $e->render(true)
        );

        unset($e->class);
        $this->assertHtmlEquals(
            "<span style=\"font-weight: bold;\" lang=\"en-us\" />",
            $e->render(true)
        );

        $e->removeAttribute('style');
        $this->assertHtmlEquals(
            "<span lang=\"en-us\" />",
            $e->render(true)
        );

        $e->setAttribute('lang', null);
        $this->assertHtmlEquals(
            "<span />",
            $e->render(true)
        );

        $e->data('id', 42);
        $this->assertHtmlEquals(
            "<span data-id=\"42\" />",
            $e->render(true)
        );

        $e->removeAttribute('data-id');
        $this->assertHtmlEquals('<span />', $e->render(true));

        $e->css(array('color' => 'red', 'font-weight' => 'bold'));
        $this->assertHtmlEquals('<span style="color: red; font-weight: bold;" />', $e->render(true));

        unset($e->style);
        $this->assertHtmlEquals('<span />', $e->render(true));

        $e->data(array('id' => 42, 'foo' => 'bar'));
        $this->assertHtmlEquals('<span data-foo="bar" data-id="42" />', $e->render(true));

        $e->removeAttribute('data-id');
        $this->assertHtmlEquals('<span data-foo="bar" />', $e->render(true));
    }

    function testAddAndRemoveClass() {

        $e = new Octopus_Html_Element('span');
        $e->addClass('foo');
        $this->assertHtmlEquals('<span class="foo" />', $e->render(true));

        $e->removeClass('foo');
        $this->assertHtmlEquals('<span />', $e->render(true));

        $e->addClass('foo bar');
        $this->assertHtmlEquals('<span class="foo bar" />', $e->render(true));

        $e->addClass(array('baz', 'bat'));
        $this->assertHtmlEquals('<span class="foo bar baz bat" />', $e->render(true));

        $e->removeClass(array('foo', 'baz'));
        $this->assertHtmlEquals('<span class="bar bat" />', $e->render(true));

        $e->removeClass('bar bat');
        $this->assertHtmlEquals('<span />', $e->render(true));

        $e->toggleClass('toggle');
        $this->assertHtmlEquals('<span class="toggle" />', $e->render(true));
        $e->addClass(' foo bar');
        $this->assertHtmlEquals('<span class="toggle foo bar" />', $e->render(true));

        $e->toggleClass('toggle');
        $this->assertHtmlEquals('<span class="foo bar" />', $e->render(true));

    }

    function testInnerText() {

        $e = new Octopus_Html_Element('span');
        $e->text('<escape> & <test>');
        $this->assertHtmlEquals(
            '<span>&lt;escape&gt; &amp; &lt;test&gt;</span>',
            $e->render(true)
        );

        $e->text('foo and bar');
        $this->assertEquals('foo and bar', $e->text());


        $child1 = new Octopus_Html_Element('span');
        $child2 = new Octopus_Html_Element('span');

        $child1->text('foo');
        $child2->text('bar');

        $e->clear();
        $e->append($child1);
        $e->append($child2);

        $this->assertEquals('foo bar', $e->text());
    }

    function testSetInnerHtml() {

        $e = new Octopus_Html_Element('span');
        $result = $e->html('<b>bold!</b>');

        $this->assertHtmlEquals('<span><b>bold!</b></span>', $e->render(true));

        $this->assertEquals($e, $result);

    }

}

?>
