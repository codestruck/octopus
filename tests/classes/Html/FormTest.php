<?php

Octopus::loadClass('Octopus_Html_TestCase');
Octopus::loadClass('Octopus_Html_Form');

class FormTest extends Octopus_Html_TestCase {

    function testNothing() {}

    function dontTestBasicFormUsage() {

        $form = new Octopus_Html_Form('testForm', 'post');
        $form->action = 'whatever.php';

        $form->add('name', 'text')
            ->autoFocus()
            ->required();

        $form->add('email')
            ->required()
            ->mustBe('email');

        $form->add('submit');

        $form->setValues(array('name' => 'Joe Blow', 'email' => 'joe@blow.com'));

        $this->assertHtmlEquals(
<<<END
<form id="testForm" method="post" action="whatever.php">
    <div id="nameField" class="name text field required">
        <label for="name">Name:</label>
        <input type="text" id="nameInput" class="name text required" name="name" value="Joe Blow" autofocus required />
    </div>
    <div id="emailField" class="email text field required">
        <label for="email">Email:</label>
        <input type="email" id="emailInput" class="email text required" name="email" value="joe@blow.com" required />
    </div>
    <div class="buttons">
        <input type="submit" />
    </div>
</form>
END
            ,
            $form->render(true)
        );

    }

    function dontTestTemplateFormFieldErrors() {

        $file = $this->saveTemplate('{name.errors}');

        $form = new Octopus_Html_Form('errors');
        $form->template = $file;
        $form->add('name')->required('Name is required.');
        $form->setValues(array());

        $this->assertHtmlEquals(
<<<END
<ul class="errors">
    <li>Name is required.</li>
</ul>
END
            ,
            $form->render(true)
        );
    }

    function dontTestTemplateFormErrors() {

        $form = new Octopus_Html_Form('errors');
        $form->template = $this->saveTemplate('{$errors}');
        $form->add('name')->required('Name is required.');
        $form->add('email')->required('Email is required.');

        $this->assertHtmlEquals(
<<<END
<ul class="errors">
    <li>Name is required.</li>
    <li>Email is required.</li>
</ul>
END
            ,
            $form->render(true)
        );
    }
}

?>
