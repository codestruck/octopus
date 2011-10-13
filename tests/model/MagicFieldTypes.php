<?php

class MoneyFieldTypeModel {

	protected $fields = array(
		'price1' => 'money',
		'price2' => 'currency',
	);

}

class MagicFieldTypesTest extends Octopus_App_TestCase {

	function testMoneyTypes() {

		$m = new MoneyFieldTypeModel();

		$f = $m->getField('price1');
		$this->assertTrue($f instanceof Octopus_Model_Field_Numeric, 'money is numeric');
		$this->assertEquals(2, $f->getOption('decimal_places'), 'money has 2 decimal places');

		$f = $m->getField('price2');
		$this->assertTrue($f instanceof Octopus_Model_Field_Numeric, 'currency is numeric');
		$this->assertEquals(2, $f->getOption('decimal_places'), 'currency has 2 decimal places');

	}

}

?>