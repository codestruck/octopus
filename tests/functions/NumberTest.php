<?php

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class NumberTest extends PHPUnit_Framework_TestCase {

    function testFormatMoney() {

        $tests = array(
            0 => '$0.00',
            '5.99' => '$5.99',
            '3.2345690' => '$3.23',
            -10 => '($10.00)',
            1999333 => '$1,999,333.00',
        );

        foreach($tests as $input => $expected) {
            $this->assertEquals($expected, format_money($input), "failed on '$input'");
        }

    }


}
