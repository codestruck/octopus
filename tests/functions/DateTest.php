<?php

class DateTest extends PHPUnit_Framework_TestCase {

	/**
	 * @dataProvider getTimespanTests
	 */
	function testTimespan($input, $expected) {
		$this->assertEquals($expected, format_time_span($input));

	}

	function getTimespanTests() {
		return array(

			array('0', '00:00'),
			array(5, '00:05'),
			array(90, '01:30'),
			array(120 * 60, '02:00:00'),
			array(86400, '01:00:00:00'),
			array(86460, '01:00:01:00'),
			array(1.123, '00:01:123')

		);
	}




}

?>