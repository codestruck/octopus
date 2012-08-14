<?php
/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class FileTest extends PHPUnit_Framework_TestCase {

    function testGetFilename() {
        $str = '/foo/bar/my_string.csv';
        $this->assertEquals('my_string.csv', get_filename($str));
    }

    function testShortGetFilename() {
        $str = '/foo/bar/my_string.csv';
        $this->assertEquals('my_string', get_filename($str, true));

        $str = '/foo/bar/my.string.csv';
        $this->assertEquals('my.string', get_filename($str, true));

    }

    function testGetExtension() {
        $str = '/foo/bar/my_string.csv';
        $this->assertEquals('.csv', get_extension($str));

        $str = '/foo/bar/my.string.csv';
        $this->assertEquals('.csv', get_extension($str));

        $str = '/foo/bar/.gitignore';
        $this->assertEquals('', get_extension($str));

        $str = '/foo/bar/exclude';
        $this->assertEquals('', get_extension($str));
    }


}

