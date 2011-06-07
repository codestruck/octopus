<?php

/**
 * Custom testcase for testing stuff that generates HTML.
 */
abstract class Octopus_Html_TestCase extends PHPUnit_Framework_TestCase {

    /**
     * Asserts two chunks of HTML are equal.
     */
    public function assertHtmlEquals($expected, $actual, $strict = false, $message = '') {

        self::staticAssertHtmlEquals($this, $expected, $actual, $strict, $message);

    }

    public static function staticAssertHtmlEquals($testCase, $expected, $actual, $strict = false, $message = '') {

        if (is_string($strict)) {
            $temp = $strict;
            $message = $strict;
            $strict =  !!$message;
        }

        $testCase->assertEquals(
            self::normalizeHtml($expected, $strict),
            self::normalizeHtml($actual, $strict),
            $message
        );
    }

    /**
     * Compares two chunks of HTML.
     */
    public static function normalizeHtml($html, $strict = false) {

        $html = trim(preg_replace('/\s+/m', ' ', $html));
        $html = str_replace("\n", '', $html);

        if ($strict) {

            // Consume whitespace before some elements
            $html = preg_replace('#\s*(<(option|tr|td|th|thead|tbody|tfoot)(\s|>))#i', '$1', $html);

            // Consume whitespace at the start of some elements
            $html = preg_replace('#(<td[^>]*>)\s+#i', '$1', $html);

            // Consume whitespace before the close of some elements
            $html = preg_replace('#\s+(</(td|th|tr)>)#i', '$1', $html);

            // Consume whitespace after some elements end
            $html = preg_replace('#(</(thead|tbody|tfoot|tr|td|th)>)\s+#i', '$1', $html);

            // Consume whitespace before the close of other elements
            $html = preg_replace('#\s*(</(select)>)#', '$1', $html);

        } else {

            // Consume all whitespace between elements
            $html = preg_replace('#>\s+<#', '><', $html);

        }

        return $html;
    }


}

?>
