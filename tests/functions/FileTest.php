<?php

class FileTest extends PHPUnit_Framework_TestCase {

    function testGetTruePath() {

        $dir = dirname(__FILE__) . '/.testGetTruePath/';

        $files = array(
            'foo.txt',
            'BAR.txt',
            'Foo/Bar/baz.txT'
        );

        $funcs = array(
            'strtoupper',
            'strtolower',
            'ucwords'
        );

        foreach($files as $f) {

            $file = $dir . $f;

            recursive_touch($file);
            $this->assertTrue(is_file($file), 'Test file does not exist.');

            foreach($funcs as $func) {
                $this->assertEquals($file, get_true_filename($func($file)), "Failed on $func for $f");
            }

        }

        `rm -rf $dir`;
    }

}


?>
