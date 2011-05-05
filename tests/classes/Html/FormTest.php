<?php

SG::loadClass('SG_Html_TestCase');

SG::loadClass('SG_Html_Form');

class FormTest extends SG_Html_TestCase {

    function testBasicFormUsage() {

        $form = new SG_Html_Form('testForm', 'post');
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

    function saveTemplate($text) {

        $templateFile = '.form_template_test.tpl';
        if (is_file($templateFile)) unlink($templateFile);

        file_put_contents($templateFile, $text);
        return $templateFile;
    }

    function testTemplateFormFieldLabel() {

        $file = $this->saveTemplate('{name.label}');

        $form = new SG_Html_Form('label');
        $form->template = $file;
        $form->add('name');

        $this->assertHtmlEquals('Name:', $form->render(true));
    }

    function testTemplateFormFieldHtml() {
        $file = $this->saveTemplate('{name.html}');

        $form = new SG_Html_Form('field');
        $form->template = $file;
        $form->add('name');

        $this->assertHtmlEquals('<input type="text" name="name" id="nameInput" class="name text" value="" />', $form->render(true));
    }

    function testTemplateFormFieldAttributes() {

        $file = $this->saveTemplate('{name.attributes}');

        $form = new SG_Html_Form('attributes');
        $form->template = $file;
        $form->add('name');

        $this->assertEquals(
            'type="text" name="name" id="nameInput" class="name text" value=""',
            $form->render(true)
        );

    }

    function testTemplateFormFieldIteration() {

        $form = new SG_Html_Form('iteration');
        $form->template = $this->saveTemplate(<<<END
{foreach from=\$fields item=f}
{\$f.name}
{/foreach}
END
        );

        $form->add('name');
        $form->add('email');
        $form->add('foo');

        $this->assertEquals(<<<END
name
email
foo
END
            ,
            $form->render(true)
        );

    }
    function testTemplateFormFieldErrors() {

        $file = $this->saveTemplate('{name.errors}');

        $form = new SG_Html_Form('errors');
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

    function testTemplateFormErrors() {

        $form = new SG_Html_Form('errors');
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

    function testTemplateFormIndividualAttributes() {

        $tests = array(
            'type' => 'text',
            'id' => 'nameInput',
            'name' => 'name',
            'class' => 'name text',
            'value' => '',
            'autofocus' => 'autofocus'
        );

        foreach($tests as $attr => $value) {

            $form = new SG_Html_Form('attr');
            $form->add('name')->autoFocus();

            $form->template = $this->saveTemplate("{\$name.$attr}");
            $this->assertEquals($value, $form->render(true), 'failed on ' . $attr);
        }

    }
}

?>
