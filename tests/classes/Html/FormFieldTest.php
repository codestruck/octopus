<?php

Octopus::loadClass('Octopus_Html_Form');
Octopus::loadClass('Octopus_Html_Form_Field');
Octopus::loadClass('Octopus_Html_TestCase');

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

    function testAutofocus() {

        $form = new Octopus_Html_Form('autofocus');
        $field = $form->add('foo')->autoFocus();

        $this->assertHtmlEquals(
            '<input type="text" id="fooInput" class="foo text" name="foo" autofocus />',
            $field->render(true)
        );


    }

}

?>
