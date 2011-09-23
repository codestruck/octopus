<?php

Octopus::loadClass('Octopus_Html_TestCase');
Octopus::loadClass('Octopus_Html_Form');

/**
 * @group Form
 */
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
                'expected' => '<a href="#" class="submit">Test</a>'
            ),

            array(
                'args' => array('reset-link', 'Reset the Form'),
                'expected' => '<a href="#" class="reset">Reset the Form</a>'
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
            ),

            array(
                'args' => array('some_image.gif', 'foo', 'bar'),
                'expected' => '<input type="image" src="some_image.gif" class="image button" name="foo" value="bar" />'
            ),
        );

        foreach($tests as $test) {

            $form = new Octopus_Html_Form('buttons');
            call_user_func_array(array($form, 'addButton'), $test['args']);

            $html = $form->render(true);
            $html = str_replace("\n", '', $html);
            $html = str_replace('<input type="hidden" name="__octopus_form_buttons_submitted" value="1" />', '', $html);
            $html = preg_replace('#<form[^>]*><div[^>]*>#', '', $html);
            $html = preg_replace('#</(div|form)>#', '', $html);

            $this->assertHtmlEquals($test['expected'], $html, var_export($test['args'], true));
        }

    }

    function testSetValuesToEmptyArray() {

        $form = new Octopus_Html_Form('emptyArray');
        $form->add('foo');

        $values = array('foo' => 'bar');
        $form->setValues($values);
        $this->assertEquals($values, $form->getValues());

        $form->setValues(array());
        $this->assertEquals(array(), $form->getValues());

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
<form id="testForm" method="post" action="whatever.php" novalidate>
    <input type="hidden" name="__octopus_form_testForm_submitted" value="1" />
    <div id="nameField" class="field name text required">
        <label for="nameInput">Name:</label>
        <input type="text" id="nameInput" class="name text required" name="name" value="Joe Blow" autofocus required />
    </div>
    <div id="emailField" class="field text email required">
        <label for="emailInput">Email:</label>
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

    function testFileField() {

        $form = new Octopus_Html_Form('testForm', 'post');

        $form->add('file', 'image');

        $this->assertHtmlEquals(
<<<END
<form id="testForm" method="post" enctype="multipart/form-data" novalidate>
    <input type="hidden" name="__octopus_form_testForm_submitted" value="1" />
    <div id="imageField" class="field image file">
        <label for="imageInput">Image:</label>
        <input type="file" id="imageInput" class="image file" name="image" />
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

        $form->setValues(array('name' => ''));
        $form->validate();

        $expected = array(

            'open_tag' => '<form id="toArray" method="post" novalidate>
<input type="hidden" name="__octopus_form_toArray_submitted" value="1" />',
            'close_tag' => '</form>',
            'attributes' => 'id="toArray" method="post" novalidate',
            'id' => 'toArray',
            'method' => 'post',
            'novalidate' => 'novalidate',
            'valid' => false,
            'errors' => array('Name is required.'),
            'fields' => array(),

            'name' => array(

                'attributes' => 'type="text" id="nameInput" class="name text required" name="name" value="" required',
                'type' => 'text',
                'id' => 'nameInput',
                'class' => 'name text required',
                'name' => 'name',
                'value' => '',
                'required' => 'required',
                'html' => trim($name->render(true)),
                'full_html' => trim($name->wrapper->render(true)),
                'valid' => false,
                'errors' => array('Name is required.'),
                'label' => array(
                    'text' => 'Name:',
                    'html' => '<label for="nameInput">Name:</label>'
                ),

            )
        );

        $expected['fields']['name'] = $expected['name'];

        $this->assertEquals(
            $expected,
            $form->toArray()
        );

        $form->setValues(array('name' => 'something <b>with markup</b>'));
        $form->validate();

        $expected = array(

                'attributes' => 'id="toArray" method="post" novalidate',
                'id' => 'toArray',
                'method' => 'post',
                'novalidate' => 'novalidate',
                'open_tag' => '<form id="toArray" method="post" novalidate>
<input type="hidden" name="__octopus_form_toArray_submitted" value="1" />',
                'close_tag' => '</form>',
                'fields' => array(),
                'valid' => true,
                'errors' => array(),
                'name' => array(

                    'attributes' => 'type="text" id="nameInput" class="name text required" name="name" value="something &lt;b&gt;with markup&lt;/b&gt;" required',
                    'type' => 'text',
                    'id' => 'nameInput',
                    'class' => 'name text required',
                    'name' => 'name',
                    'value' => 'something &lt;b&gt;with markup&lt;/b&gt;',
                    'required' => 'required',
                    'html' => trim($name->render(true)),
                    'valid' => true,
                    'errors' => array(),
                    'label' => array(
                        'text' => 'Name:',
                        'html' => '<label for="nameInput">Name:</label>'
                    ),
                    'full_html' => trim($name->wrapper->render(true)),

                )
            );

        $expected['fields']['name'] = $expected['name'];

        $this->assertEquals(
            $expected,
            $form->toArray()
        );
    }

    function testToArrayDontOverWriteFormKeysWithField() {

        $form = new Octopus_Html_Form('noOverwrite');
        $closeTag = $form->add('text', 'close_tag');

        $expected = array(

                'attributes' => 'id="noOverwrite" method="post" novalidate',
                'id' => 'noOverwrite',
                'method' => 'post',
                'novalidate' => 'novalidate',
                'open_tag' => '<form id="noOverwrite" method="post" novalidate>
<input type="hidden" name="__octopus_form_noOverwrite_submitted" value="1" />',
                'close_tag' => '</form>',
                'valid' => true,
                'errors' => array(),
                'fields' => array(
                    'close_tag' => array(

                        'attributes' => 'type="text" id="close_tagInput" class="close_tag text" name="close_tag"',
                        'type' => 'text',
                        'id' => 'close_tagInput',
                        'class' => 'close_tag text',
                        'name' => 'close_tag',
                        'html' => trim($closeTag->render(true)),
                        'valid' => true,
                        'errors' => array(),
                        'label' => array(
                            'text' => 'Close Tag:',
                            'html' => '<label for="close_tagInput">Close Tag:</label>'

                        ),
                        'full_html' => trim($closeTag->wrapper->render(true))

                    )
                ),

            );

        $this->assertEquals($expected, $form->toArray());


    }

    function testWasSubmitted() {

        $form = new Octopus_Html_Form('wasSubmitted', 'post');
        $form->add('foo');
        $this->assertFalse($form->wasSubmitted());


        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_POST['__octopus_form_wasSubmitted_submitted'] = 1;
        $form = new Octopus_Html_Form('wasSubmitted', 'post');
        $form->add('foo');
        $this->assertFalse($form->reset()->wasSubmitted(), 'should be false w/ wrong request method');

        $_POST['foo'] = 'bar';
        $form = new Octopus_Html_Form('wasSubmitted', 'post');
        $_POST['__octopus_form_wasSubmitted_submitted'] = 1;
        $form->add('foo');
        $this->assertFalse($form->reset()->wasSubmitted(), 'should be false w/ wrong request method, even if data is present');

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $form = new Octopus_Html_Form('wasSubmitted', 'post');
        $_POST['__octopus_form_wasSubmitted_submitted'] = 1;
        $form->add('foo');
        $this->assertTrue($form->reset()->wasSubmitted(), 'should be true w/ proper request method');

    }

    function testNotSubmittedSetValues() {

        $form = new Octopus_Html_Form('wasNotSubmitted', 'post');
        $form->add('foo');
        $this->assertFalse($form->wasSubmitted());

        $form->setValues(array('for' => 'thefoovalue'));
        $this->assertFalse($form->wasSubmitted());

    }

    function testWasSubmitted2() {

        $_POST = array();

        $form = new Octopus_Html_Form('wasSubmitted', 'post');
        $form->add('foo');
        $this->assertFalse($form->wasSubmitted());

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['foo'] = 'bar';
        $_POST['__octopus_form_wasSubmitted_submitted'] = 1;

        $this->assertTrue($form->wasSubmitted());

    }

    function testSetValuesWithObject() {

        $obj = new StdClass();
        $obj->foo = 'bar';
        $obj->name = 'Joe';

        $form = new Octopus_Html_Form('setValuesWithObject');
        $form->add('foo')->required();
        $form->add('name')->required();

        $this->assertFalse($form->validate(), 'should not validate w/ no data');

        $form->setValues($obj);
        $this->assertTrue($form->validate(), 'should validate after setValues() call');

        $values = $form->getValues();
        $this->assertEquals(
            array(
                'foo' => 'bar',
                'name' => 'Joe'
            ),
            $values
        );

    }

    function testGetFieldByName() {

        $form = new Octopus_Html_Form('getfield');

        $textarea = $form->add('textarea', 'foo');

        $this->assertEquals($textarea, $form->getField('foo'));
        $this->assertNull($form->getField('missing'));

        $field = new Octopus_Html_Form_Field('input', 'password', 'password', '', array());
        $customWrapper1 = new Octopus_Html_Element('div');
        $customWrapper2 = new Octopus_Html_Element('div');
        $customWrapper2->append($field);
        $customWrapper1->append($customWrapper2);
        $form->add($customWrapper1);

        $this->assertEquals($field, $form->getField('password'));
    }

    function testSecurityTokenMissing() {

        $user_id = 99;
        $form = new Octopus_Html_Form('security_test');
        $form->secure($user_id);
        $form->add('name');

        $_POST['name'] = 'foo';
        $_POST['__octopus_form_security_test_submitted'] = 1;
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $this->assertTrue($form->submitted());
        $this->assertFalse($form->validate());

    }

    function testSecurityTokenInvalid() {

        $user_id = 99;
        $form = new Octopus_Html_Form('security_test');
        $form->secure($user_id);
        $form->add('name');

        $_POST['name'] = 'foo';
        $_POST['__security_token'] = get_security_token($user_id, 'security_test') . 'ALTERED';
        $_POST['__octopus_form_security_test_submitted'] = 1;
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $this->assertTrue($form->submitted());
        $this->assertFalse($form->validate());

    }

    function testSecurityTokenGood() {

        $user_id = 99;
        $form = new Octopus_Html_Form('security_test');
        $this->assertEquals('', $form->getSecurityTokenFieldName(), 'Unsecured forms should not have a security token field name.');

        $form->secure($user_id);
        $form->add('name');

        $_POST['name'] = 'foo';
        $_POST[$form->getSecurityTokenFieldName()] = get_security_token($user_id, 'security_test');
        $_POST['__octopus_form_security_test_submitted'] = 1;
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $this->assertTrue($form->submitted());
        $this->assertTrue($form->validate());

    }

    function testSimulateSubmission() {

    	$form = new Octopus_Html_Form('simulateSubmission');
    	$form->add('name');

    	$form->submit(array('name' => 'foo'));
    	$this->assertTrue($form->wasSubmitted(), 'form is marked as submitted after submit() call');
    }

    function testSimulateSubmissionValidation() {

    	$form = new Octopus_Html_Form('simulateSubmissionValidation');
    	$form->add('name')->required();

    	$form->submit(array('name' => 'foo'));
    	$this->assertTrue($form->validate(), 'form validates after submit() call');

    	$form->submit(array('name' => ''));
    	$this->assertFalse($form->validate(), 'form fails validation after invalid data passed to submit()');

    }

}

?>
