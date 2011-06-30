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

<form id="myform" method="post" novalidate>
<input type="hidden" name="__form_myform_submitted" value="1" />
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

    function testRadioValidate() {

        $form = new Octopus_Html_Form('test');
        $field = $form->add('radio', 'color')->required();
        $field->addOption('pink', 'Pink');
        $field->addOption('blue', 'Blue');
        $field->addOption('green', 'Green');

        $_POST['color'] = array('pink');
        $_POST['__form_test_submitted'] = 1;
        $_SERVER['REQUEST_METHOD'] = 'post';

        $this->assertTrue($form->submitted(), 'The form was submitted');

        $this->assertTrue($form->validate(), 'The form was validated');


        $expect = <<<END

<form id="test" method="post" novalidate>
<input type="hidden" name="__form_test_submitted" value="1" />
<div id="colorField" class="field color radio required">
<label>Color:</label>
<div class="colorRadioGroup radioGroup required">
<div class="radioItem colorRadioItem colorpinkRadioItem">
<label for="colorInputpink">Pink</label>
<input type="radio" id="colorInputpink" class="color radio valuepink required" name="color" value="pink" checked /></div>
<div class="radioItem colorRadioItem colorblueRadioItem">
<label for="colorInputblue">Blue</label>
<input type="radio" id="colorInputblue" class="color radio valueblue required" name="color" value="blue" /></div>
<div class="radioItem colorRadioItem colorgreenRadioItem">
<label for="colorInputgreen">Green</label>
<input type="radio" id="colorInputgreen" class="color radio valuegreen required" name="color" value="green" /></div></div></div></form>
END;

        $this->assertHtmlEquals(
            $expect,
            $form->render(true)
        );

    }

    function testRadioValues() {

        $form = new Octopus_Html_Form('test');
        $field = $form->add('radio', 'color');
        $field->addOption('pink', 'Pink');
        $field->addOption('blue', 'Blue');
        $field->addOption('green', 'Green');

        $_POST['color'] = array('pink');
        $_POST['__form_test_submitted'] = 1;
        $_SERVER['REQUEST_METHOD'] = 'post';

        $this->assertTrue($form->submitted(), 'The form was submitted');

        $form->setValues($form->getValues());

        $expect = <<<END

<form id="test" method="post" novalidate>
<input type="hidden" name="__form_test_submitted" value="1" />
<div id="colorField" class="field color radio">
<label>Color:</label>
<div class="colorRadioGroup radioGroup">
<div class="radioItem colorRadioItem colorpinkRadioItem">
<label for="colorInputpink">Pink</label>
<input type="radio" id="colorInputpink" class="color radio valuepink" name="color" value="pink" checked /></div>
<div class="radioItem colorRadioItem colorblueRadioItem">
<label for="colorInputblue">Blue</label>
<input type="radio" id="colorInputblue" class="color radio valueblue" name="color" value="blue" /></div>
<div class="radioItem colorRadioItem colorgreenRadioItem">
<label for="colorInputgreen">Green</label>
<input type="radio" id="colorInputgreen" class="color radio valuegreen" name="color" value="green" /></div></div></div></form>
END;

        $this->assertHtmlEquals(
            $expect,
            $form->render(true)
        );

    }

}

?>
