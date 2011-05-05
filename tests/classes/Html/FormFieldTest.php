<?php

Octopus::loadClass('Octopus_Html_Form_Field');

Octopus::loadClass('Octopus_Html_TestCase');

class FormFieldTest extends Octopus_Html_TestCase {

    function testRenderTextField() {

        $name = Octopus_Html_Form_Field::create('name', 'text', array('autofocus' => true));

        $this->assertHtmlEquals(
            '<input type="text" name="name" id="nameInput" class="name text" autofocus />',
            $name->render(true)
        );

        $this->assertHtmlEquals(
            '<label for="name">Name:</label>',
            $name->label->render(true)
        );

    }

    function testRenderEmailField() {

        $email = Octopus_Html_Form_Field::create('email');

        $this->assertHtmlEquals(
            '<input type="email" name="email" id="emailInput" class="text email" />',
            $email->render(true)
        );

    }

}

?>
