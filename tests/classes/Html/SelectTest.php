<?php

SG::loadClass('SG_Html_TestCase');

SG::loadClass('SG_Html_Form');
SG::loadClass('SG_Html_Form_Select');

class SelectTest extends SG_Html_TestCase {

    function testSelectExplicitOptions() {

        $s = new SG_Html_Form_Select('test');

        $s->addOption(42, 'The Answer')->addClass('ultimateQuestion');
        $s->addOptions(array(
            1 => 'One',
            2 => 'Two',
            3 => 'Three',
        ));
        $s->addOption('Hi There', array('class' => 'sameTextAndValue'));

        $expected = <<<END
<select name="test" id="testInput" class="test select">
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

        $s = new SG_Html_Form_Select('test');
        $s->addOptions(array(
           1 => 'Foo',
           2 => 'Bar'
        ));

        $s->val(2);
        $this->assertEquals(2, $s->val());

        $expected = <<<END
<select name="test" id="testInput" class="test select">
    <option value="1">Foo</option>
    <option value="2" selected>Bar</option>
</select>
END;

        $this->assertHtmlEquals($expected, $s->render(true));

        $s->val(1);
        $expected = <<<END
<select name="test" id="testInput" class="test select">
    <option value="1" selected>Foo</option>
    <option value="2">Bar</option>
</select>
END;

        $this->assertHtmlEquals($expected, $s->render(true));

        $s->setAttribute('value', 'missing');
        $expected = <<<END
<select name="test" id="testInput" class="test select">
    <option value="1">Foo</option>
    <option value="2">Bar</option>
</select>
END;

        $this->assertHtmlEquals($expected, $s->render(true));

    }

    function testSelectUsingFunctionForOptions() {

        $sel = new SG_Html_Form_Select('test');
        $sel->addOptions(array($this, '_test_get_options'));

        $expected = <<<END
<select name="test" id="testInput" class="test select">
    <option value="1">Foo</option>
    <option value="2">Bar</option>
</select>
END;
        $this->assertHtmlEquals($expected, $sel->render(true));

    }

    function _test_get_options($sel) {

        $this->assertTrue(!!$sel, 'SG_Html_Form_Select should be passed to function factory');
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

            $sel = new SG_Html_Form_Select('test');
            $sel->addOptions(array($foo, $bar));

            $expected = <<<END
<select name="test" id="testInput" class="test select">
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

            $sel = new SG_Html_Form_Select('test');
            $sel->addOptions(array($foo, $bar));

            $expected = <<<END
<select name="test" id="testInput" class="test select">
    <option value="{$foo->$idField}">{$foo->$textField}</option>
    <option value="{$bar->$idField}">{$bar->$textField}</option>
</select>
END;

            $this->assertHtmlEquals($expected, $sel->render(true));

        }
    }
}

?>