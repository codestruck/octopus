<?php

Octopus::loadClass('Octopus_Html_TestCase');
Octopus::loadClass('Octopus_Html_Form');

class FormTest extends Octopus_Html_TestCase {

    function testAddButtons() {

        $tests = array(

            array(
                'args' => array('submit'),
                'expected' => '<button type="submit" class="submit button" />'
            ),

            array(
                'args' => array('reset'),
                'expected' => '<button type="reset" class="reset button" />'
            ),

            array(
                'args' => array('submit-link', 'foo', 'bar', 'Test'),
                'expected' => '<a href="#" class="submit button">Test</a>'
            ),

            array(
                'args' => array('reset-link', 'Reset the Form'),
                'expected' => '<a href="#" class="reset button">Reset the Form</a>'
            ),

            array(
                'args' => array(array('type' => 'submit', 'name' => 'foo', 'value' => 'bar', 'label' => 'Test')),
                'expected' => '<button type="submit" class="submit button" name="foo" value="bar">Test</button>'
            ),

            array(
                'args' => array('submit', 'Submit the Form'),
                'expected' => '<button type="submit" class="submit button">Submit the Form</button>',
            ),

            array(
                'args' => array('reset', 'Reset the Form'),
                'expected' => '<button type="reset" class="reset button">Reset the Form</button>',
            ),

            array(
                'args' => array('submit', array('name' => 'foo', 'value' => 'bar', 'label' => 'Test')),
                'expected' => '<button type="submit" class="submit button" name="foo" value="bar">Test</button>'
            )


        );

        foreach($tests as $test) {

            $form = new Octopus_Html_Form('buttons');
            call_user_func_array(array($form, 'addButton'), $test['args']);

            $html = $form->render(true);
            $html = preg_replace('#<form[^>]*><div[^>]*>#', '', $html);
            $html = preg_replace('#</(div|form)>#', '', $html);

            $this->assertHtmlEquals($test['expected'], $html, var_export($test['args'], true));
        }

    }

    function testBasicFormUsage() {

        $form = new Octopus_Html_Form('testForm', 'post');
        $form->action = 'whatever.php';

        $form->add('name')
            ->autoFocus()
            ->required();

        $form->add('email')
            ->required()
            ->mustBe('email');

        $form->addButton('submit');

        $form->setValues(array('name' => 'Joe Blow', 'email' => 'joe@blow.com'));

        $this->assertHtmlEquals(
<<<END
<form id="testForm" method="post" action="whatever.php">
    <div id="nameField" class="field name text">
        <label for="name">Name:</label>
        <input type="text" id="nameInput" class="name text required" name="name" value="Joe Blow" autofocus required />
    </div>
    <div id="emailField" class="field text email">
        <label for="email">Email:</label>
        <input type="email" id="emailInput" class="text email required" name="email" value="joe@blow.com" required />
    </div>
    <div class="buttons">
        <button type="submit" class="submit button" />
    </div>
</form>
END
            ,
            $form->render(true)
        );

    }

    function testToArrayBasic() {

        $form = new Octopus_Html_Form('toArray');
        $name = $form->add('name')->required();

        $form->validate(array('name' => ''));

        $this->assertEquals(
            array(

                'form' => array(
                    'attributes' => 'id="toArray" method="post"',
                    'id' => 'toArray',
                    'method' => 'post',
                    'valid' => false,
                    'errors' => array('Name is required.')
                ),

                'name' => array(

                    'attributes' => 'type="text" id="nameInput" class="name text required" name="name" value="" required',
                    'type' => 'text',
                    'id' => 'nameInput',
                    'class' => 'name text required',
                    'name' => 'name',
                    'value' => '',
                    'required' => 'required',
                    'html' => $name->render(true),
                    'valid' => false,
                    'errors' => array('Name is required.')

                )
            ),
            $form->toArray()
        );

        $form->validate(array('name' => 'something <b>with markup</b>'));

        $this->assertEquals(
            array(

                'form' => array(
                    'attributes' => 'id="toArray" method="post"',
                    'id' => 'toArray',
                    'method' => 'post',
                    'valid' => true,
                    'errors' => array()
                ),

                'name' => array(

                    'attributes' => 'type="text" id="nameInput" class="name text required" name="name" value="something &lt;b&gt;with markup&lt;/b&gt;" required',
                    'type' => 'text',
                    'id' => 'nameInput',
                    'class' => 'name text required',
                    'name' => 'name',
                    'value' => 'something &lt;b&gt;with markup&lt;/b&gt;',
                    'required' => 'required',
                    'html' => $name->render(true),
                    'valid' => true,
                    'errors' => array()

                )
            ),
            $form->toArray()
        );

    }

}

?>
