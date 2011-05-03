<?php

Octopus::loadClass('Octopus_Html_Form');

class FormValidationTest extends PHPUnit_Framework_TestCase {

    /*
    function testCallbackValidation() {

        $form = new Octopus_Html_Form('callback');
        $form->add('foo')->passesCallback();

    }
    */

    function testRegexValidate() {

        $form = new Octopus_Html_Form('regex');

        $form->add('foo')
            ->mustMatch('/^\s*\d+(-\d+)?\s*$/');

        $tests = array(

            '' => true,
            '     ' => true,
            '98225' => true,
            'hi there' => false

        );

        foreach($tests as $input => $expectedResult) {

            $result = $form->validate(array('foo' => $input));
            $this->assertEquals($expectedResult, $result->success, "Failed on '$input'");

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

            $result = $form->validate(array('foo' => $input));
            $this->assertEquals($expectedResult, $result->success, "Failed on '$input'");

        }

        $result = $form->validate(array('foo' => 10.001));
        $this->assertFalse($result->success, "Failed on 10.001");

        $result = $form->validate(array('foo' => 9.99));
        $this->assertTrue($result->success, "Failed on 9.99");

        $result = $form->validate(array('foo' => 1.01));
        $this->assertTrue($result->success, "Failed on 1.01");

        $result = $form->validate(array('foo' => .99));
        $this->assertFalse($result->success, "Failed on .99");


    }

    function testRequiredValidation() {

        $form = new Octopus_Html_Form('reqForm');
        $field = $form->add('name', 'text')->required();

        $this->assertTrue(count($field->getRules()) === 1, 'Field should have a required rule on it');

        $tests = array(
            '' => false,
            '     ' => false,
            '0' => true,
            'false' => true
        );

        foreach($tests as $input => $expectedResult) {
            $result = $form->validate(array('name' => $input));
            $this->assertEquals($expectedResult, $result->success, "Failed on '$input'");
        }

        $field->required(false);
        $this->assertTrue(count($field->getRules()) === 0, 'Field should not have a required rule on it');

        foreach($tests as $input => $expectedResult) {
            $result = $form->validate(array('name' => $input));
            $this->assertEquals(true, $result->success, "Failed on '$input' w/ no rule");
        }

    }

}

?>
