<?php

class NumberTest extends PHPUnit_Framework_TestCase {

    function testFormatMoney() {

        $tests = array(
            0 => '$0.00',
            '5.99' => '$5.99',
            '3.2345690' => '$3.23',
            -10 => '($10.00)',
        );

        foreach($tests as $input => $expected) {
            $this->assertEquals($expected, format_money($input), "failed on '$input'");
        }

    }


}

?>
