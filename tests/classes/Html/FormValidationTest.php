<?php

define('NO_INPUT', '--NO INPUT--');

Octopus::loadClass('Octopus_Html_Form');

class FormValidationTest extends PHPUnit_Framework_TestCase {

    function testEmailValidation() {

        $tests = array(
            NO_INPUT => true,
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

        $data = array();
        if ($input !== NO_INPUT) $data['foo'] = $input;

        $result = $form->validate($data);

        $input = ($input === NO_INPUT ? $input : "'$input'");

        $this->assertEquals($expectedResult, $result->success, "Failed on $input");
    }

    function testCallbackValidation() {

        $tests = array(
            NO_INPUT => true,
            '' => true,
            '      ' => true,
            'pass' => true,
            'fail' => false

        );

        $form = new Octopus_Html_Form('callback');
        $form->add('foo')->mustPass(array($this, '_test_callback'));

        foreach($tests as $input => $expectedResult) {
            $this->runValidationTest($form, $input, $expectedResult);
        }
    }

    function _test_callback($field, $input, $data) {

        $this->assertTrue(!!$field, "Field is missing");
        $this->assertTrue(!!$data, "Data is missing");

        $this->assertEquals('foo', $field->name, "field name is wrong");
        $this->assertTrue(isset($data['foo']), 'no key in data');

        return $input == 'pass';
    }

    function testRegexValidate() {

        $tests = array(
            NO_INPUT => true,
            '' => true,
            '     ' => true,
            '98225' => true,
            'hi there' => false

        );

        $form = new Octopus_Html_Form('regex');
        $form->add('foo')
            ->mustMatch('/^\s*\d+(-\d+)?\s*$/');


        foreach($tests as $input => $expectedResult) {
            $this->runValidationTest($form, $input, $expectedResult);
        }

    }

    function testRangeValidation() {

        $tests = array(
            NO_INPUT => true,
            '' => true,
            '   ' => true,
            'plain text' => false,
            -10 => false,
            0 => false,
            10 => true,
        );

        $form = new Octopus_Html_Form('range');
        $form->add('foo')
            ->between(1, 10);

        foreach($tests as $input => $expectedResult) {
            $this->runValidationTest($form, $input, $expectedResult);
        }

        $this->runValidationTest($form, 10.001, false);
        $this->runValidationTest($form, 9.99, true);
        $this->runValidationTest($form, 1.01, true);
        $this->runValidationTest($form, .99, false);
    }

    function testRequiredValidation() {

        $tests = array(
            NO_INPUT => false,
            '' => false,
            '     ' => false,
            '0' => true,
            'false' => true
        );

        $form = new Octopus_Html_Form('reqForm');
        $field = $form->add('foo', 'text')->required();
        $this->assertTrue(count($field->getRules()) === 1, 'Field should have a required rule on it');

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
        $form->mustPass(array($this, '_test_validate_form'));

        $this->assertTrue($form->validate(array('x' => 'pass', 'y' => 'pass'))->success);
        $this->assertFalse($form->validate(array('x' => 'fail', 'y' => 'fail'))->success);

    }

    function _test_validate_form($form, $data) {

        $this->assertTrue($form instanceof Octopus_Html_Form, '$form is not an Octopus_Html_Form');
        $this->assertTrue(!!$data, '$data is empty.');

        return $data['x'] == 'pass' && $data['y'] == 'pass';

    }

}

?>
