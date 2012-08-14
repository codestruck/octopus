<?php
/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
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

    function testGetDay() {
        $this->assertEquals(956214000, get_day('2000-04-20'));
        $this->assertEquals(956214000, get_day(strtotime('2000-04-20') + 400));
    }

    function testAddDays() {
        $base = 956214000;
        $this->assertEquals($base + (86400 * 2), add_days('2000-04-20', 2));
        $this->assertEquals($base - (86400 * 2), add_days('2000-04-20', -2));
    }

}
