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

        $expected = self::normalizeHtml($expected, $strict);
        $actual = self::normalizeHtml($actual, $strict);

        if (strcmp($expected, $actual) !== 0) {

            $len = min(strlen($expected), strlen($actual));

            for($i = 0; $i < $len; $i++) {

                if (strcmp($expected[$i], $actual[$i]) !== 0) {

                    $around = 23;
                    $start = max(0, $i - $around);
                    $end = min($len, $i + $around);

                    $expectedExcerpt = substr($expected, $start, $end - $start);
                    $actualExcerpt = substr($actual, $start, $end - $start);

                    $arrow = str_repeat('-', $i - $start) . '^';

                    if ($start > 0) {
                        $expectedExcerpt = '...' . $expectedExcerpt;
                        $actualExcerpt = '...' . $actualExcerpt;
                        $arrow = '---' . $arrow;
                    }

                    if ($end < $len) {
                        $expectedExcerpt .= '...';
                        $actualExcerpt .= '...';
                    }


                    dump_r(
<<<END
Strings differ at position $i:
Expected:   $expectedExcerpt
Actual:     $actualExcerpt
            $arrow

END

                    );
                    break;

                }

            }

        }

        $testCase->assertEquals(
            $expected,
            $actual,
            $message
        );
    }

    /**
     * Compares two chunks of HTML.
     */
    public static function normalizeHtml($html, $strict = false) {

        if (class_exists('Octopus_Html_Element') && $html instanceof Octopus_Html_Element) {
            $html = $html->render(true);
        }

        $html = trim(preg_replace('/\s+/m', ' ', $html));
        $html = str_replace("\n", '', $html);

        if ($strict) {

            // Consume whitespace before some elements
            $html = preg_replace('#\s*(<(option|tr|td|th|thead|tbody|tfoot)(\s|>))#i', '$1', $html);

            // Consume whitespace at the start of some elements
            $html = preg_replace('#(<(td|style|script)[^>]*>)\s+#i', '$1', $html);

            // Consume whitespace before the close of some elements
            $html = preg_replace('#\s+(</(td|th|tr|style|script)>)#i', '$1', $html);

            // Consume whitespace after some elements end
            $html = preg_replace('#(</(thead|tbody|tfoot|tr|td|th)>)\s+#i', '$1', $html);

            // Consume whitespace before the close of other elements
            $html = preg_replace('#\s*(</(select)>)#', '$1', $html);

        } else {

            // Consume all whitespace between elements
            $html = preg_replace('#>\s+<#', '><', $html);
        }

        $html = preg_replace('#(<(style|script)[^>]*>)\s+#i', '$1', $html);
        $html = preg_replace('#\s+(</(style|script)>)#i', '$1', $html);

        $html = str_replace('<', "\n<", $html);


        return $html;
    }


}

?>
