<?php

/**
 * @group Html
 * @group Form
 */
class FormFieldTest extends Octopus_Html_TestCase {

	function testGetArrayFieldByName() {

		$form = new Octopus_Html_Form('getArrayField');
		$sel = $form->add('select', 'name');
		$sel->addOptions(array('foo' => 'bar', 'baz' => 'bat'));
		$sel->multiple = true;

		$this->assertEquals('name[]', $sel->name);
		$this->assertSame($sel, $form->getField('name'));
		$this->assertSame($sel, $form->getField('name[]'));

		$form->submit(array('name' => array('bar', 'bat')));
		$this->assertEquals(
			array(
				'name' => array('bar', 'bat')
			),
			$form->getValues()
		);


	}

    function testAddFieldToForm() {

        $form = new Octopus_Html_Form('testForm');
        $form->add('test');

        $f = $form->getField('test');
        $this->assertTrue($f instanceof Octopus_Html_Form_Field, 'field found');

    }

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
                'wrapper' => array(
                    'open_tag' => $textarea->wrapper->renderOpenTag() . '>',
                    'close_tag' => $textarea->wrapper->renderCloseTag('test')
                ),
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

    function testFieldNiceName() {

        $form = new Octopus_Html_Form('niceName');

        $foo = $form->add('foo');
        $this->assertEquals('Foo', $foo->niceName());

        $foo = $form->add('foo_bar');
        $this->assertEquals('Foo Bar', $foo->niceName());

        $foo = $form->add('foo2')->niceName('Custom name');
        $this->assertEquals('Custom name', $foo->niceName());

        $foo = $form->add('foo3')->label('Custom name with colon:');
        $this->assertEquals('Custom name with colon', $foo->niceName());

        $foo = $form->add('foo4')->niceName('Should not change with label')->label('whatever');
        $this->assertEquals('Should not change with label', $foo->niceName());

        $foo = $form->add('foo5')->label('First label');
        $this->assertEquals('First label', $foo->niceName());
        $foo->label('Change label updates nice name');
        $this->assertEquals('Change label updates nice name', $foo->niceName());

    }


}

?>
