<?php

/**
 * Custom testcase for testing stuff that generates HTML.
 */
abstract class SG_Html_TestCase extends PHPUnit_Framework_TestCase {

    /**
     * Asserts two chunks of HTML are equal.
     */
    public function assertHtmlEquals($expected, $actual, $message = '') {

        $this->assertEquals(
            self::normalizeHtml($expected),
            self::normalizeHtml($actual),
            $message
        );
    }

    /**
     * Compares two chunks of HTML.
     */
    public static function normalizeHtml($html) {

        $html = trim(preg_replace('/\s+/m', ' ', $html));

        // Consume whitespace before some elements
        $html = preg_replace('#\s*(<option(\s|>))#i', '$1', $html);

        // Consume whitespace before the close of other elements
        $html = preg_replace('#\s*(</(select)>)#', '$1', $html);

        return $html;
    }


}

?>
