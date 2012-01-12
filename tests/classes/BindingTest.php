<?php

class IoCTestClassA { }
class IoCTestClassB { }

/**
 * Tests for the IoC functionality of the Octopus class.
 */
class BindingTest extends Octopus_App_TestCase {

	function testBindToClass() {

		Octopus::bind('IoCTestClassA', 'IoCTestClassB');
		$instance = Octopus::create('IoCTestClassA');

		$this->assertTrue($instance instanceof IoCTestClassB, 'simple bind succeeds');

		Octopus::unbind('IoCTestClassA', 'IoCTestClassB');
		$instance = Octopus::create('IoCTestClassA');
		$this->assertTrue($instance instanceof IoCTestClassA, 'explicit unbind succeeds');

	}

	function testUnbind() {

		Octopus::bind('IoCTestClassA', 'IoCTestClassB');
		Octopus::unbind('IoCTestClassA');

		$instance = Octopus::create('IoCTestClassA');
		$this->assertTrue($instance instanceof IoCTestClassA, 'unbind() succeeds');

	}

	function testBindToInstance() {

		$b = new IoCTestClassB();
		$b->foo = 'bar';

		Octopus::bind('IoCTestClassA', $b);

		$a = Octopus::create('IoCTestClassA');
		$this->assertSame($a, $b, 'create() returns bound instance');

	}

	/**
	 * @expectedException Octopus_Exception
	 */
	function testCreateWithCtorArgsFailsWhenBoundToInstance() {

		$b = new IoCTestClassB();
		$b->foo = 'bar';

		Octopus::bind('IoCTestClassA', $b);
		Octopus::create('IoCTestClassA', array('arg1', 'arg2'));

	}

}

?>