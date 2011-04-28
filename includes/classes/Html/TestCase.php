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

        $whitespacePattern = '/\s{2,}/';
        $html = trim(preg_replace($whitespacePattern, '', $html));

        return $html;
    }


}

?>
