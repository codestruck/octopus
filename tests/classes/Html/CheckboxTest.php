<?php

class CheckboxTest extends Octopus_Html_TestCase {

    function testCheckboxChecked() {

        $form = new Octopus_Html_Form('checkbox');
        $checkbox = $form->add('checkbox', 'foo');

        $this->assertFalse($checkbox->checked(), 'checked should be false');
        $this->assertFalse($checkbox->val(), 'val should be false');
        $this->assertNull($checkbox->getAttribute('checked'), 'getAttribute should return null');

        $checkbox->val(true);
        $this->assertTrue($checkbox->checked(), 'checked should be true');
        $this->assertTrue($checkbox->val(), 'val should be true');
        $this->assertTrue($checkbox->getAttribute('checked'), 'getAttribute should return true');
        $checkbox->val(false);
        $this->assertFalse($checkbox->checked(), 'checked should be false');
        $this->assertFalse($checkbox->val(), 'val should be false');
        $this->assertNull($checkbox->getAttribute('checked'), 'getAttribute should return null');


        $checkbox->checked(true);
        $this->assertTrue($checkbox->checked(), 'checked should be true');
        $this->assertTrue($checkbox->val(), 'val should be true');
        $this->assertTrue($checkbox->getAttribute('checked'), 'getAttribute should return true');
        $checkbox->checked(false);
        $this->assertFalse($checkbox->checked(), 'checked should be false');
        $this->assertFalse($checkbox->val(), 'val should be false');
        $this->assertNull($checkbox->getAttribute('checked'), 'getAttribute should return null');


        $checkbox->setAttribute('value', true);
        $this->assertTrue($checkbox->checked(), 'checked should be true');
        $this->assertTrue($checkbox->val(), 'val should be true');
        $this->assertTrue($checkbox->getAttribute('checked'), 'getAttribute should return true');
        $checkbox->setAttribute('value', false);
        $this->assertFalse($checkbox->checked(), 'checked should be false');
        $this->assertFalse($checkbox->val(), 'val should be false');
        $this->assertNull($checkbox->getAttribute('checked'), 'getAttribute should return null');

        $checkbox->setAttribute('checked', true);
        $this->assertTrue($checkbox->checked(), 'checked should be true');
        $this->assertTrue($checkbox->val(), 'val should be true');
        $this->assertTrue($checkbox->getAttribute('checked'), 'getAttribute should return true');
        $checkbox->setAttribute('checked', false);
        $this->assertFalse($checkbox->checked(), 'checked should be false');
        $this->assertFalse($checkbox->val(), 'val should be false');
        $this->assertFalse($checkbox->getAttribute('checked'), 'getAttribute should return false');


    }

    function testCheckboxValuesAreBoolean() {

        $form = new Octopus_Html_Form('checkbox', 'post');
        $check = $form->add('checkbox', 'foo')->val(true);

        $this->assertHtmlEquals(
            '<input type="checkbox" id="fooInput" class="foo checkbox" name="foo" checked />',
            $check->render(true)
        );

        $form->submit(array('foo' => 'on'));
        $vals = $form->getValues();
        $this->assertTrue($vals['foo'], 'when on, checkbox value should be true');

        $form = new Octopus_Html_Form('checkbox');
        $check = $form->add('checkbox', 'foo')->val(true);
        unset($_POST['foo']);
        $vals = $form->getValues(true);
        $this->assertFalse($vals['foo'], 'when no value posted, checkbox value should be false');

    }

    function testRenderCheckboxesNormal() {

        $form = new Octopus_Html_Form('test');
        $form->add('checkbox', 'optin', 'Are you in');

        $expect = <<<END

<form id="test" method="post" novalidate>
<input type="hidden" name="__octform" value="9f2f820f4736ddf1f9e5d21994559d53" />
<div id="optinField" class="field optin checkbox">
<input type="checkbox" id="optinInput" class="optin checkbox" name="optin" />
<label for="optinInput">Are you in</label>
</div>
</form>
END;

        $this->assertHtmlEquals(
            $expect,
            $form->render(true)
        );

    }

    function testRenderCheckboxesMultiple() {

        $form = new Octopus_Html_Form('test');
        $form->add('checkbox', 'colors[]', 'Colors', array('value' => 'blue'));
        //$form->add('checkbox', 'colors[]', 'pink');

        $expect = <<<END

<form id="test" method="post" novalidate>
<input type="hidden" name="__octform" value="9f2f820f4736ddf1f9e5d21994559d53" />
<div id="colorsBlueField" class="field colors valueblue checkbox">
<input type="checkbox" id="colorsBlueInput" class="colors valueblue checkbox" name="colors[]" value="blue" />
<label for="colorsBlueInput">Colors</label>
</div></form>
END;

        $this->assertHtmlEquals(
            $expect,
            $form->render(true)
        );

    }

    function testRenderCheckboxesMultipleValues() {

        $form = new Octopus_Html_Form('test');
        $form->add('checkbox', 'colors[]', 'Colors', array('value' => 'blue'));
        $form->add('checkbox', 'colors[]', 'Colors', array('value' => 'green'));
        $form->add('checkbox', 'colors[]', 'Colors', array('value' => 'pink'));
        $form->setValues(array(
            'colors' => array('pink', 'blue'),
        ));

        $expect = <<<END

<form id="test" method="post" novalidate>
<input type="hidden" name="__octform" value="9f2f820f4736ddf1f9e5d21994559d53" />
<div id="colorsBlueField" class="field colors valueblue checkbox">
    <input type="checkbox" id="colorsBlueInput" class="colors valueblue checkbox" name="colors[]" value="blue" checked />
    <label for="colorsBlueInput">Colors</label>
</div>
<div id="colorsGreenField" class="field colors valuegreen checkbox">
    <input type="checkbox" id="colorsGreenInput" class="colors valuegreen checkbox" name="colors[]" value="green" />
    <label for="colorsGreenInput">Colors</label>
</div>
<div id="colorsPinkField" class="field colors valuepink checkbox">
    <input type="checkbox" id="colorsPinkInput" class="colors valuepink checkbox" name="colors[]" value="pink" checked />
    <label for="colorsPinkInput">Colors</label>
</div>
</form>
END;

        $this->assertHtmlEquals(
            $expect,
            $form->render(true)
        );

    }

    function testGetValuesMultipleCheckboxes() {

        $form = new Octopus_Html_Form('test');
        $form->add('checkbox', 'colors[]', 'Colors', array('value' => 'blue'));
        $form->add('checkbox', 'colors[]', 'Colors', array('value' => 'green'));
        $form->add('checkbox', 'colors[]', 'Colors', array('value' => 'pink'));

        $values = array(
            'colors' => array('blue', 'pink'),
        );

        $form->submit($values);
        $this->assertEquals($values, $form->getValues());
    }

    function testValidateMultipleCheckboxes() {

        $form = new Octopus_Html_Form('test');
        $form->add('checkbox', 'colors[]', 'Colors', array('value' => 'blue'))->required();
        $form->add('checkbox', 'colors[]', 'Colors', array('value' => 'green'))->required();
        $form->add('checkbox', 'colors[]', 'Colors', array('value' => 'pink'))->required();

        $_POST['colors'] = array('pink', 'blue');
        $_POST['__octform'] = $form->getSignature();
        $_SERVER['REQUEST_METHOD'] = 'post';

        $this->assertTrue($form->submitted(), 'The form was submitted');
        $this->assertTrue($form->validate(), 'The form was validated');


        $expect = <<<END

<form id="test" method="post" novalidate>
<input type="hidden" name="__octform" value="9f2f820f4736ddf1f9e5d21994559d53" />
<div id="colorsBlueField" class="field colors valueblue checkbox required">
    <input type="checkbox" id="colorsBlueInput" class="colors valueblue checkbox required" name="colors[]" value="blue" checked />
    <label for="colorsBlueInput">Colors</label>
</div>
<div id="colorsGreenField" class="field colors valuegreen checkbox required">
    <input type="checkbox" id="colorsGreenInput" class="colors valuegreen checkbox required" name="colors[]" value="green" />
    <label for="colorsGreenInput">Colors</label>
</div>
<div id="colorsPinkField" class="field colors valuepink checkbox required">
    <input type="checkbox" id="colorsPinkInput" class="colors valuepink checkbox required" name="colors[]" value="pink" checked />
    <label for="colorsPinkInput">Colors</label>
</div>
</form>
END;

        $this->assertHtmlEquals(
            $expect,
            $form->render(true)
        );


    }

}

?>