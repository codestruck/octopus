<?php

/**
 * @group Html
 * @group Form
 */
class FormFieldTest extends Octopus_Html_TestCase {

    function testRenderTextField() {

        $form = new Octopus_Html_Form('text');
        $name = $form->add('text', 'name', array('autofocus' => true));

        $this->assertHtmlEquals(
            '<input type="text" id="nameInput" class="name text" name="name" autofocus />',
            $name->render(true)
        );


    }

    function testRenderEmailField() {

        $email = Octopus_Html_Form_Field::create('email');

        $this->assertHtmlEquals(
            '<input type="email" id="emailInput" class="text email" name="email" />',
            $email->render(true)
        );

    }

    function testRenderPasswordField() {

        $password = Octopus_Html_Form_Field::create('password', 'my_password');

        $this->assertHtmlEquals(
            '<input type="password" id="my_passwordInput" class="text my_password password" name="my_password" />',
            $password->render(true)
        );

    }

    function testAutofocus() {

        $form = new Octopus_Html_Form('autofocus');
        $field = $form->add('foo')->autoFocus();

        $this->assertHtmlEquals(
            '<input type="text" id="fooInput" class="foo text" name="foo" autofocus />',
            $field->render(true)
        );


    }

    function testClassAndRequired() {

        $form = new Octopus_Html_Form('req');
        $field = $form->add('text', 'required_days')->required()->addClass('nice');

        $this->assertHtmlEquals(
            '<input type="text" id="required_daysInput" class="required_days text required nice" name="required_days" required />',
            $field->render(true)
        );


    }

    function testTextareaToArray() {

        $form = new Octopus_Html_Form('textarea');
        $textarea = $form->add('textarea', 'foo');
        $textarea->val('<b>test</b>');

        $this->assertEquals(
            array(
                'attributes' => 'id="fooInput" class="foo textarea" name="foo"',
                'id' => 'fooInput',
                'class' => 'foo textarea',
                'name' => 'foo',
                'html' => trim($textarea->render(true)),
                'full_html' => trim($textarea->wrapper->render(true)),
                'label' => array(
                    'text' => 'Foo:',
                    'html' => '<label for="fooInput">Foo:</label>',
                ),
                'valid' => true,
                'errors' => array(),
                'value' => '&lt;b&gt;test&lt;/b&gt;',
            ),
            $textarea->toArray()
        );

    }

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

    	$this->markTestSkipped();return;

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

    	$this->markTestSkipped();return;

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

    	$this->markTestSkipped();return;

        $form = new Octopus_Html_Form('test');
        $form->add('checkbox', 'colors[]', 'Colors', array('value' => 'blue'));
        $form->add('checkbox', 'colors[]', 'Colors', array('value' => 'green'));
        $form->add('checkbox', 'colors[]', 'Colors', array('value' => 'pink'));

        $values = array(
            'colors' => array('pink', 'blue'),
        );

        $form->submit($values);
        $this->assertEquals($values, $form->getValues());
    }

    function testValidateMultipleCheckboxes() {

    	$this->markTestSkipped();return;

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
