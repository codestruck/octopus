<?php

Octopus::loadClass('Octopus_Html_Form');
Octopus::loadClass('Octopus_Html_Form_Field');
Octopus::loadClass('Octopus_Html_TestCase');

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
            '<input type="password" id="my_passwordInput" class="text my-password" name="my_password" />',
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
                'html' => $textarea->render(true),
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

        $_POST['foo'] = 'on';
        $vals = $form->getValues(true);
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

<form id="test" method="post">
<div id="optinField" class="field optin checkbox">
<label for="optinInput">Are you in</label>
<input type="checkbox" id="optinInput" class="optin checkbox" name="optin" /></div></form>
END;

        $this->assertEquals(
            $expect,
            $form->render(true)
        );

    }

    function testRenderCheckboxesMultiple() {

        $form = new Octopus_Html_Form('test');
        $form->add('checkbox', 'colors[]', 'Colors', array('value' => 'blue'));
        //$form->add('checkbox', 'colors[]', 'pink');

        $expect = <<<END

<form id="test" method="post">
<div id="colorsBlueField" class="field colors valueblue checkbox">
<label for="colorsBlueInput">Colors</label>
<input type="checkbox" id="colorsBlueInput" class="colors valueblue checkbox" name="colors[]" value="blue" /></div></form>
END;

        $this->assertEquals(
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

<form id="test" method="post">
<div id="colorsBlueField" class="field colors valueblue checkbox">
<label for="colorsBlueInput">Colors</label>
<input type="checkbox" id="colorsBlueInput" class="colors valueblue checkbox" name="colors[]" value="blue" checked /></div>
<div id="colorsGreenField" class="field colors valuegreen checkbox">
<label for="colorsGreenInput">Colors</label>
<input type="checkbox" id="colorsGreenInput" class="colors valuegreen checkbox" name="colors[]" value="green" /></div>
<div id="colorsPinkField" class="field colors valuepink checkbox">
<label for="colorsPinkInput">Colors</label>
<input type="checkbox" id="colorsPinkInput" class="colors valuepink checkbox" name="colors[]" value="pink" checked /></div></form>
END;

        $this->assertEquals(
            $expect,
            $form->render(true)
        );

    }

    function testGetValuesMultipleCheckboxes() {
        
        $form = new Octopus_Html_Form('test');
        $form->add('checkbox', 'colors[]', 'Colors', array('value' => 'blue'));
        $form->add('checkbox', 'colors[]', 'Colors', array('value' => 'green'));
        $form->add('checkbox', 'colors[]', 'Colors', array('value' => 'pink'));

        $_POST['colors'] = array('pink', 'blue');
        
        $values = array(
            'colors' => array('pink', 'blue'),
        );
        
        $this->assertEquals($values, $form->getValues());
    }



}

?>
