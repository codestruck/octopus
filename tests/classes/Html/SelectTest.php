<?php

Octopus::loadClass('Octopus_Html_TestCase');
Octopus::loadClass('Octopus_Html_Form');
Octopus::loadClass('Octopus_Html_Form_Field_Select');

class SelectTest extends Octopus_Html_TestCase {

    function testAddToForm() {

        $form = new Octopus_Html_Form('select');

        $form->add('select', 'foo');

        $this->assertHtmlEquals(
            <<<END
<form id="select" method="post">
    <div id="fooField" class="field foo select">
        <label for="foo">Foo:</label>
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
}

?>
