<?php

/**
 * @group Html
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class ElementTests extends Octopus_Html_TestCase {

	function testTextEscapesProperly() {

		$e = new Octopus_Html_Element('span');
		$e->text(<<<END
'quotes should be intact, <> but "angle brackets" should be escaped'
END
		);

		$this->assertHtmlEquals(
			<<<END
<span>'quotes should be intact, &lt;&gt; but "angle brackets" should be escaped'</span>
END
			,
			$e->render(true)
		);

	}

	function testDontEscapeAttributesDeep() {

		$el = new Octopus_Html_Element('span');
		$el->title = '&gt;';

		$child = new Octopus_Html_Element('span');
		$child->title = '&lt;';

		$el->append($child);

		$this->assertHtmlEquals(
			<<<END
<span title="&gt;"><span title="&lt;" /></span>
END
			,
			$el->render(true, Octopus_Html_Element::DONT_ESCAPE_ATTRIBUTES)
		);

	}

	function testByDefaultEscapeAttributes() {

		$el = new Octopus_Html_Element('span');
		$el->title = "<'this is a test'>";

		$this->assertHtmlEquals(
			<<<END
<span title="&lt;&#039;this is a test&#039;&gt;" />
END
			,
			$el->render(true)
		);

	}

	function testOptionallyDontEscapeAttributes() {

		$el = new Octopus_Html_Element('span');
		$el->title = "&lt;This is a test&gt;";

		$this->assertHtmlEquals(
			<<<END
<span title="&lt;This is a test&gt;" />
END
			,
			$el->render(true, Octopus_Html_Element::DONT_ESCAPE_ATTRIBUTES)
		);

	}

    function testAppendTo() {

        $parent = new Octopus_Html_Element('div');
        $span = new Octopus_Html_Element('span');

        $this->assertSame($span, $span->text('test')->appendTo($parent));
        $this->assertHtmlEquals(
            <<<END
<div><span>test</span></div>
END
            ,
            $parent->render(true)
        );

    }

    function testPrependTo() {

        $parent = new Octopus_Html_Element('div');
        $child1 = new Octopus_Html_Element('span');
        $child2 = new Octopus_Html_Element('span');

        $child2->text('child 2')->appendTo($parent);

        $this->assertSame($child1, $child1->text('child 1')->prependTo($parent));

        $this->assertHtmlEquals(
            <<<END
<div><span>child 1</span><span>child 2</span></div>
END
            ,
            $parent->render(true)
        );

    }




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

    function testAddRemoveClassWithDashes() {

    	$e = new Octopus_Html_Element('span');

    	$e->addClass('my-span');
    	$this->assertEquals('my-span', $e->class);

    	$e->addClass('span', 'my');
    	$this->assertEquals('my-span span my', $e->class);

    	$e->removeClass('span');
    	$this->assertEquals('my-span my', $e->class);

    	$e->removeClass('my');
    	$this->assertEquals('my-span', $e->class);

    	$e->removeClass('my-span');
    	$this->assertEquals('', $e->class);


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
