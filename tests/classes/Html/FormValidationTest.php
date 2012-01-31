<?php

define('NO_INPUT', '--NO INPUT--');

/**
 * @group Html
 * @group Form
 */
class FormValidationTest extends PHPUnit_Framework_TestCase {

    function testEmailValidation() {

        $tests = array(
            '' => true,
            '    ' => true,
            'matthinz@solegraphics.com' => true,
            'matthinz+test@gmail.com' => true,
            'aasdf' => false,
            'asd23@' => false,
            'no@no' => false,
            'no @ no.com' => false
        );

        $form = new Octopus_Html_Form('email');
        $form->add('foo')->mustBe('email');

        foreach($tests as $input => $expectedResult) {

            $this->runValidationTest($form, $input, $expectedResult);
        }

    }

    function runValidationTest($form, $input, $expectedResult) {

        $data = array('foo' => $input);

        $form->setValues($data);

        $this->assertEquals($data, $form->getValues(), "failed on $input");

        $result = $form->validate();

        $input = ($input === NO_INPUT ? $input : "'$input'");

        $this->assertEquals($expectedResult, $result, "Failed on $input");
    }

    function testCallbackValidation() {

        $form = new Octopus_Html_Form('callback');
        $form->add('foo')->mustPass(array($this, '_test_callback'));

        $tests = array(

            'pass' => true,
            'fail' => false,
            '  pass' => false

        );

        foreach($tests as $input => $expectedResult) {
            $this->runValidationTest($form, $input, $expectedResult);
        }
    }

    function _test_callback($value, $data, $field) {

        $this->assertTrue(!!$field, '$field should be present');
        $this->assertTrue($value !== null, '$value should not be null');
        $this->assertTrue(is_array($data), '$data should be an array');

        if (isset($data[$field->name])) {
            $this->assertEquals($value, $data[$field->name], 'value is off');
        }

        if (!isset($data[$field->name])) {
            return true;
        }

        return $data[$field->name] == 'pass';
    }

    function testRegexValidate() {

        $form = new Octopus_Html_Form('regex');
        $form->add('foo')->mustMatch('/^\d{5}(-\d+)?$/');

        $tests = array(
            '' => false,
            '     ' => false,
            '98225' => true,
            'hi there' => false

        );

        foreach($tests as $input => $expectedResult) {
            $this->runValidationTest($form, $input, $expectedResult);
        }

    }

    function testStopCheckingRulesOnFailure() {

        $form = new Octopus_Html_Form('shortCircuit');
        $form->add('foo')
            ->required()
            ->mustMatch('/\d+/');

        $form->submit(array('foo' => ''));
        $form->validate($result);
        $this->assertFalse($result->success);
        $this->assertEquals(1, count($result->errors));

    }

    function testMustMatchOtherField() {

        $tests = array(
            array('', '', true),
            array('foo', '', false),
            array('foo', 'bar', false),
            array('foo', 'foo', true)
        );

        foreach($tests as $params) {

            list($email, $confirm_email, $succeeds) = $params;

            $form = new Octopus_Html_Form('mustMatchOtherField');
            $form->add('email');
            $form->add('confirm_email')->mustMatch('email');

            $form->submit(compact('email', 'confirm_email'));
            $this->assertEquals($succeeds, $form->validate(), "'$email', '$confirm_email' " . ($succeeds ? 'succeeds' : 'fails'));
        }

    }

    function testRangeValidation() {

        $form = new Octopus_Html_Form('range');

        $form->add('foo')
             ->between(1, 10);

        $tests = array(
            '' => true,
            '   ' => true,
            'plain text' => false,
            -10 => false,
            0 => false,
            10 => true,
        );

        foreach($tests as $input => $expectedResult) {
            $this->runValidationTest($form, $input, $expectedResult);
        }

        $this->runValidationTest($form, 10.001, false);
        $this->runValidationTest($form, 9.99, true);
        $this->runValidationTest($form, 1.01, true);
        $this->runValidationTest($form, .99, false);
    }

    function testRequiredValidation() {

        $form = new Octopus_Html_Form('reqForm');
        $field = $form->add('foo')->required();

        $this->assertTrue(count($field->getRules()) === 1, 'Field should have a required rule on it');

        $tests = array(
            '' => false,
            '     ' => false,
            '0' => true,
            'false' => true
        );

        foreach($tests as $input => $expectedResult) {
            $this->runValidationTest($form, $input, $expectedResult);
        }

        $field->required(false);
        $this->assertTrue(count($field->getRules()) === 0, 'Field should not have a required rule on it');

        foreach($tests as $input => $expectedResult) {
            $this->runValidationTest($form, $input, true);
        }

    }

    function testFormValidation() {

        $form = new Octopus_Html_Form('validation');
        $form->add('x');
        $form->add('y');
        $form->mustPass(array($this, '_test_validate_form'));

        $this->assertTrue($form->setValues(array('x' => 'pass', 'y' => 'pass'))->validate());
        $this->assertFalse($form->setValues(array('x' => 'fail', 'y' => 'fail'))->validate());

    }

    function _test_validate_form($data, $form) {

        $this->assertTrue($form instanceof Octopus_Html_Form, '$form is not an Octopus_Html_Form');
        $this->assertTrue(!!$data, '$data is empty.');

        return $data['x'] == 'pass' && $data['y'] == 'pass';

    }

    function testMinLengthValidation() {

        $form = new Octopus_Html_Form('minLength');
        $form->add('foo')->minLength(5);

        $form->submit(array('foo' => ''));
        $this->assertTrue($form->validate(), 'Validation should succeed with empty input?');

        for ($i = 1; $i < 5; $i++) {
            $form->submit(array('foo' => str_repeat('x', $i)));
            $this->assertFalse($form->validate(), "Validation should fail with $i characters");
        }

        $form->submit(array('foo' => 'xxxxx'));
        $this->assertTrue($form->validate(), 'validation should succeed with 5 characters');

    }

    function testMaxLengthValidation() {

        $form = new Octopus_Html_Form('maxLength');
        $form->add('foo')->maxLength(5);

        $form->submit(array('foo' => ''));

        for ($i = 5; $i >= 0; $i--) {
            $form->submit(array('foo' => str_repeat('x', $i)));
            $this->assertTrue($form->validate(), "Validation should succeed with $i characters");
        }

        $form->submit(array('foo' => 'xxxxxx'));
        $this->assertFalse($form->validate(), 'validation should fail with > 5 characters');

    }



}

?>
