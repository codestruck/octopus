<?php

Octopus::loadClass('Octopus_Html_TestCase');
Octopus::loadClass('Octopus_Html_Form');
Octopus::loadClass('Octopus_Html_Form_Field_Select');


/**
 * @group Html
 * @group Form
 * @group Radio
 */
class RadioTest extends Octopus_Html_TestCase {

    function testFullRadio() {

        $form = new Octopus_Html_Form('myform');
        $form->add('radio', 'test')->addOption(42, 'The Answer');

        $expected = <<<END

<form id="myform" method="post">
<div id="testField" class="field test radio">
<label>Test:</label>
<div class="testRadioGroup radioGroup">
<div class="radioItem testRadioItem test42RadioItem">
<label for="testInput42">The Answer</label>
<input type="radio" id="testInput42" class="test radio value42" name="test" value="42" /></div></div></div></form>
END;

        $this->assertEquals($expected, $form->render(true));

    }

    function testRadioBasic() {

        $s = Octopus_Html_Form_Field::create('radio', 'test');

        $s->addOption(42, 'The Answer');

        $expected = <<<END

<div class="testRadioGroup radioGroup">
<div class="radioItem testRadioItem test42RadioItem">
<label for="testInput42">The Answer</label>
<input type="radio" id="testInput42" class="test radio value42" name="test" value="42" /></div></div>
END;

        $this->assertEquals($expected, $s->render(true));

    }

    function testRadioExplicitOptions() {

        $s = Octopus_Html_Form_Field::create('radio', 'test');

        $s->addOption(42, 'The Answer')->addClass('ultimateQuestion');
        $s->addOptions(array(
            1 => 'One',
            2 => 'Two',
            3 => 'Three',
        ));
        $s->addOption('Hi There', array('class' => 'sameTextAndValue'));

        $expected = <<<END

<div class="testRadioGroup radioGroup">
<div class="radioItem testRadioItem test42RadioItem ultimateQuestion">
<label for="testInput42">The Answer</label>
<input type="radio" id="testInput42" class="test radio value42" name="test" value="42" /></div>
<div class="radioItem testRadioItem test1RadioItem">
<label for="testInput1">One</label>
<input type="radio" id="testInput1" class="test radio value1" name="test" value="1" /></div>
<div class="radioItem testRadioItem test2RadioItem">
<label for="testInput2">Two</label>
<input type="radio" id="testInput2" class="test radio value2" name="test" value="2" /></div>
<div class="radioItem testRadioItem test3RadioItem">
<label for="testInput3">Three</label>
<input type="radio" id="testInput3" class="test radio value3" name="test" value="3" /></div>
<div class="radioItem testRadioItem testHi_ThereRadioItem sameTextAndValue">
<label for="testInputHi_There">Hi There</label>
<input type="radio" id="testInputHi_There" class="test radio valueHi_There" name="test" value="Hi There" /></div></div>
END;

        $this->assertEquals($expected, $s->render(true));

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

}

?>
