<?php

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
<input type="hidden" name="__octform" value="17e07c52e417cdf64bef5b73c6115667" />
<div id="testField" class="field test radio">
<label>Test:</label>
<div class="testRadioGroup radioGroup">
<div class="radioItem test-radio-item test-42-radio-item">
<label for="test-input-42">The Answer</label>
<input type="radio" id="test-input-42" class="test radio value-42" name="test" value="42" /></div></div></div></form>
END;

        $this->assertHtmlEquals($expected, $form->render(true));

    }

    function testRadioBasic() {

        $s = Octopus_Html_Form_Field::create('radio', 'test');

        $s->addOption(42, 'The Answer');

        $expected = <<<END

<div class="testRadioGroup radioGroup">
<div class="radioItem test-radio-item test-42-radio-item">
<label for="test-input-42">The Answer</label>
<input type="radio" id="test-input-42" class="test radio value-42" name="test" value="42" /></div></div>
END;

        $this->assertEquals($expected, $s->render(true));

    }

    function testRadioExplicitOptions() {

        $s = Octopus_Html_Form_Field::create('radio', 'test');

        $s->addOption(42, 'The Answer')->addClass('ultimate-question');
        $s->addOptions(array(
            1 => 'One',
            2 => 'Two',
            3 => 'Three',
        ));
        $s->addOption('Hi There', array('class' => 'same-text-and-value'));

        $expected = <<<END

<div class="testRadioGroup radioGroup">
<div class="radioItem test-radio-item test-42-radio-item ultimate-question">
<label for="test-input-42">The Answer</label>
<input type="radio" id="test-input-42" class="test radio value-42" name="test" value="42" /></div>
<div class="radioItem  test-radio-item test-1-radio-item">
<label for="test-input-1">One</label>
<input type="radio" id="test-input-1" class="test radio value-1" name="test" value="1" /></div>
<div class="radioItem  test-radio-item test-2-radio-item">
<label for="test-input-2">Two</label>
<input type="radio" id="test-input-2" class="test radio value-2" name="test" value="2" /></div>
<div class="radioItem  test-radio-item test-3-radio-item">
<label for="test-input-3">Three</label>
<input type="radio" id="test-input-3" class="test radio value-3" name="test" value="3" /></div>
<div class="radioItem  test-radio-item test-hi-there-radio-item same-text-and-value">
<label for="test-input-hi-there">Hi There</label>
<input type="radio" id="test-input-hi-there" class="test radio value-hi-there" name="test" value="Hi There" /></div></div>
END;

        $this->assertHtmlEquals($expected, $s->render(true));

    }

    function testRadioValidate() {

        $form = new Octopus_Html_Form('test');
        $field = $form->add('radio', 'color')->required();
        $field->addOption('pink', 'Pink');
        $field->addOption('blue', 'Blue');
        $field->addOption('green', 'Green');

        $form->submit(array('color' => 'pink'));

        $this->assertEquals('pink', $field->val());
        $this->assertEquals(array('color' => 'pink'), $form->getValues());

        $valid = $form->validate($result);

        $this->assertTrue($valid, 'The form was validated');

        $sig = $form->getSignature();
        $expect = <<<END

<form id="test" method="post" novalidate>
<input type="hidden" name="__octform" value="$sig" />
<div id="colorField" class="field color radio required">
<label>Color:</label>
<div class="colorRadioGroup radioGroup required">
<div class="radioItem color-radio-item colorpink-radio-item">
<label for="color-inputpink">Pink</label>
<input type="radio" id="color-inputpink" class="color radio valuepink required" name="color" value="pink" checked /></div>
<div class="radioItem color-radio-item colorblue-radio-item">
<label for="color-inputblue">Blue</label>
<input type="radio" id="color-inputblue" class="color radio valueblue required" name="color" value="blue" /></div>
<div class="radioItem color-radio-item colorgreen-radio-item">
<label for="color-inputgreen">Green</label>
<input type="radio" id="color-inputgreen" class="color radio valuegreen required" name="color" value="green" /></div></div></div></form>
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

        $_POST['color'] = 'pink';
        $_POST['__octform'] = $form->getSignature();
        $_SERVER['REQUEST_METHOD'] = 'post';

        $this->assertTrue($form->submitted(), 'The form was submitted');

        $form->setValues($form->getValues());

        $expect = <<<END

<form id="test" method="post" novalidate>
<input type="hidden" name="__octform" value="9f2f820f4736ddf1f9e5d21994559d53" />
<div id="colorField" class="field color radio">
<label>Color:</label>
<div class="colorRadioGroup radioGroup">
<div class="radioItem color-radio-item colorpink-radio-item">
<label for="color-inputpink">Pink</label>
<input type="radio" id="color-inputpink" class="color radio valuepink" name="color" value="pink" checked /></div>
<div class="radioItem color-radio-item colorblue-radio-item">
<label for="color-inputblue">Blue</label>
<input type="radio" id="color-inputblue" class="color radio valueblue" name="color" value="blue" /></div>
<div class="radioItem color-radio-item colorgreen-radio-item">
<label for="color-inputgreen">Green</label>
<input type="radio" id="color-inputgreen" class="color radio valuegreen" name="color" value="green" /></div></div></div></form>
END;

        $this->assertHtmlEquals(
            $expect,
            $form->render(true)
        );

    }

}

?>
