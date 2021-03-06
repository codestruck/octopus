<?php
/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class SelectTest extends Octopus_Html_TestCase {

    function testOptionsMultipleArgs() {

        $form = new Octopus_Html_Form('select');
        $sel = $form->add('select', 'foo');

        $sel->addOptions('', 'One', 'Two', 'Three');

        $this->assertEquals(
            array('', 'One', 'Two', 'Three'),
            array_keys($sel->getOptions())
        );

    }

    function testAddToForm() {

        $form = new Octopus_Html_Form('select');

        $form->add('select', 'foo');

        $this->assertHtmlEquals(
            <<<END
<form id="select" method="post" novalidate>
    <input type="hidden" name="__octform" value="6ae16343985c3e1c751bfdea064ddec9" />
    <div id="fooField" class="field foo select">
        <label for="fooInput">Foo:</label>
        <select id="fooInput" class="foo select" name="foo">
        </select>
    </div>
</form>
END
            ,
            $form->render(true)
        );

    }

    function testSelectExplicitOptions() {

        $s = Octopus_Html_Form_Field::create('select', 'test');

        $s->addOption(42, 'The Answer')->addClass('ultimateQuestion');
        $s->addOptions(array(
            1 => 'One',
            2 => 'Two',
            3 => 'Three',
        ));
        $s->addOption('Hi There', array('class' => 'sameTextAndValue'));

        $expected = <<<END
<select id="testInput" class="test select" name="test">
    <option class="ultimateQuestion" value="42">The Answer</option>
    <option value="1">One</option>
    <option value="2">Two</option>
    <option value="3">Three</option>
    <option class="sameTextAndValue" value="Hi There">Hi There</option>
</select>
END;

        $this->assertHtmlEquals($expected, $s->render(true));

    }

    function testSelectValue() {
        $s = Octopus_Html_Form_Field::create('select', 'test');
        $s->addOptions(array(
           1 => 'Foo',
           2 => 'Bar'
        ));

        $s->val(2);
        $this->assertEquals(2, $s->val());

        $expected = <<<END
<select id="testInput" class="test select" name="test">
    <option value="1">Foo</option>
    <option value="2" selected>Bar</option>
</select>
END;

        $this->assertHtmlEquals($expected, $s->render(true));

        $s->val(1);
        $expected = <<<END
<select id="testInput" class="test select" name="test">
    <option value="1" selected>Foo</option>
    <option value="2">Bar</option>
</select>
END;

        $this->assertHtmlEquals($expected, $s->render(true));

        $s->setAttribute('value', 'missing');
        $expected = <<<END
<select id="testInput" class="test select" name="test">
    <option value="1">Foo</option>
    <option value="2">Bar</option>
</select>
END;

        $this->assertHtmlEquals($expected, $s->render(true));

    }

    function testSelectUsingFunctionForOptions() {

        $sel = Octopus_Html_Form_Field::create('select', 'test');
        $sel->addOptions(array($this, '_test_get_options'));

        $expected = <<<END
<select id="testInput" class="test select" name="test">
    <option value="1">Foo</option>
    <option value="2">Bar</option>
</select>
END;
        $this->assertHtmlEquals($expected, $sel->render(true));

    }

    function _test_get_options($sel) {

        $this->assertTrue(!!$sel, 'Octopus_Html_Form_Field_Select should be passed to function factory');
        $this->assertEquals('test', $sel->name);

        return array(
          1 => 'Foo',
          2 => 'Bar'
        );

    }

    function testSelectUsingArrays() {

        $fieldsToTry = array(
            array('id', 'text'),
            array('some_id', 'name'),
            array('value', 'description')
        );

        foreach($fieldsToTry as $fields) {

            $idField = array_shift($fields);
            $textField = array_shift($fields);

            $foo = array($idField => 1, $textField => 'Foo');
            $bar = array($idField => 2, $textField => 'Bar');

            $sel = Octopus_Html_Form_Field::create('select', 'test');
            $sel->addOptions(array($foo, $bar));

            $expected = <<<END
<select id="testInput" class="test select" name="test">
    <option value="{$foo[$idField]}">{$foo[$textField]}</option>
    <option value="{$bar[$idField]}">{$bar[$textField]}</option>
</select>
END;

$this->assertHtmlEquals($expected, $sel->render(true), "failed on {$idField}, {$textField}");

        }
    }


    function testSelectUsingObjects() {

        $foo = new StdClass();
        $bar = new StdClass();

        $fieldsToTry = array(
            array('id', 'text'),
            array('some_id', 'name'),
            array('value', 'description')
        );

        foreach($fieldsToTry as $fields) {

            $idField = array_shift($fields);
            $textField = array_shift($fields);

            $foo = new StdClass();
            $foo->$idField = 1;
            $foo->$textField = 'Foo';

            $bar = new StdClass();
            $bar->$idField = 2;
            $bar->$textField = 'Bar';

            $sel = Octopus_Html_Form_Field::create('select', 'test');
            $sel->addOptions(array($foo, $bar));

            $expected = <<<END
<select id="testInput" class="test select" name="test">
    <option value="{$foo->$idField}">{$foo->$textField}</option>
    <option value="{$bar->$idField}">{$bar->$textField}</option>
</select>
END;

            $this->assertHtmlEquals($expected, $sel->render(true));

        }
    }

    function testToArray() {

        $form = new Octopus_Html_Form('toArray');
        $select = $form->add('select', 'foo');
        $select->addOptions(array(1 => 'Option 1', 2 => 'Option 2'));

        $this->assertEquals(
            array(
                'attributes' => 'id="fooInput" class="foo select" name="foo"',
                'id' => 'fooInput',
                'class' => 'foo select',
                'name' => 'foo',
                'html' => trim($select->render(true)),
                'label' => array(
                   'text' => 'Foo:',
                   'html' => '<label for="fooInput">Foo:</label>'
                ),
                'valid' => true,
                'errors' => array(),
                'options' => array(1 => 'Option 1', 2 => 'Option 2'),
                'label' => array(
                    'text' => 'Foo:',
                    'html' => '<label for="fooInput">Foo:</label>'
                ),
                'full_html' => trim($select->wrapper->render(true)),
                'wrapper' => array(
                    'open_tag' => $select->wrapper->renderOpenTag() . '>',
                    'close_tag' => $select->wrapper->renderCloseTag('test')
                ),
            ),
            $select->toArray()
        );

    }

    function testMultipleSelection() {

        $select = Octopus_Html_Form_Field::create('select', 'test');

        $select->addOptions(array(
            'a' => 'foo',
            'b' => 'bar',
            'c' => 'baz'
        ));

        $select->multiple = true;

        $this->assertEquals('test[]', $select->name, 'Name updated');

        /*
        $this->assertHtmlEquals(
            <<<END
<select id="testInput" class="test select" name="test" multiple>
<option value="a" selected>foo</option>
<option value="b">bar</option>
<option value="c">baz</option>
</select>
END
            ,
            $select->render(true),
            'first item selected by default'
        );
        */

        $select->val(array('a', 'b'));
        $this->assertHtmlEquals(
            <<<END
<select id="testInput" class="test select" name="test[]" multiple>
<option value="a" selected>foo</option>
<option value="b" selected>bar</option>
<option value="c">baz</option>
</select>
END
            ,
            $select->render(true),
            'Set multiple values using array to val()'
        );
        $this->assertEquals(array('a', 'b'), $select->val(), 'Multiple values returned by val()');


        $select->val('a', 'c');
        $this->assertHtmlEquals(
            <<<END
<select id="testInput" class="test select" name="test[]" multiple>
<option value="a" selected>foo</option>
<option value="b">bar</option>
<option value="c" selected>baz</option>
</select>
END
            ,
            $select->render(true),
            'Set multiple values as multiple args to val()'
        );


    }

    function testLoadMultipleValues() {

        $select = Octopus_Html_Form_Field::create('select', 'test');
        $select->addOptions(array('a' => 'foo', 'b' => 'bar', 'c' => 'baz'));
        $select->multiple = true;

        $values = array('test' => array('a', 'c'));

        $select->loadValue($values);
        $this->assertEquals(array('a', 'c'), $select->val());

    }


}

