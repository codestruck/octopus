<?php

class NumericTestModel extends Octopus_Model {

    protected $fields = array(

    	'intfield' => 'numeric',
    	'decimalfield' => array('type' => 'numeric', 'decimal_places' => 4)


    );

}

class NumericTest extends Octopus_App_TestCase {

    function testIntsInitializeTo0() {

    	$m = new NumericTestModel();
    	$this->assertSame(0, $m->intfield);

    }

    function testDecimalsInitializeTo0() {

    	$m = new NumericTestModel();
    	$this->assertSame(0.0, $m->decimalfield);

    }

    function testStringConvertsToInt() {

    	$m = new NumericTestModel();

    	$m->intfield = '0';
    	$this->assertSame(0, $m->intfield);

    	$m->intfield = '3.000';
    	$this->assertSame(3, $m->intfield);

    	$m->intfield = '5.95059';
    	$this->assertSame(5, $m->intfield);

    }

    function testNonnumericStringDoesNotChangeValue() {

    	$m = new NumericTestModel();
    	$m->intfield = 'invalid thing';
    	$this->assertSame(0, $m->intfield);

    	$m->intfield = 500;
    	$m->intfield = 'something else invalid';
    	$this->assertSame(500, $m->intfield);

    }

    function testStringConvertsToDecimal() {

    	$m = new NumericTestModel();

    	$m->decimalfield = '0';
    	$this->assertSame(0.0, $m->decimalfield);

    	$m->decimalfield = '3.000';
    	$this->assertSame(3.00, $m->decimalfield);

    	$m->decimalfield = '5.9509';
    	$this->assertSame(5.9509, $m->decimalfield);

    }

    function testNonnumericStringDoesNotChangeDecimalValue() {

    	$m = new NumericTestModel();
    	$m->decimalfield = 'invalid thing';
    	$this->assertSame(0.0, $m->decimalfield);

    	$m->decimalfield = 500.75;
    	$m->decimalfield = 'something else invalid';
    	$this->assertSame(500.75, $m->decimalfield);

    }

    function testSetIntWithThousandsSeparator() {

    	$m = new NumericTestModel();
    	$m->intfield = '9,203';
    	$this->assertEquals(9203, $m->intfield);

    }

    function testSetDecimalWithThousandsSeparator() {

    	$m = new NumericTestModel();
    	$m->decimalfield = '9,203.40';
    	$this->assertEquals(9203.4, $m->decimalfield);

    }

    function testSetIntWithCurrencySymbol() {

    	$m = new NumericTestModel();
    	$m->intfield = ' $1200';
    	$this->assertEquals(1200, $m->intfield);

    }

    function testSetDecimalWithCurrencySymbol() {

    	$m = new NumericTestModel();
    	$m->decimalfield = ' $1200.39';
    	$this->assertEquals(1200.39, $m->decimalfield);

    }

    function testCutOffDecimalPlaces() {

    	$m = new NumericTestModel();
    	$m->decimalfield = 8.2039589404893;
    	$this->assertSame(8.2040, $m->decimalfield);

    }


}

?>