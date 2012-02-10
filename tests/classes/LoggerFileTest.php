<?php

Octopus::loadClass('Octopus_Logger_File');

class Logger_File_Test extends PHPUnit_Framework_TestCase
{

    function testFileExists()
    {
        $file = tempnam('/tmp', 'asdf');
        $msg = 'unique new york';

        $l = new Octopus_Logger_File($file);
        $l->log($msg);

        $contents = file_get_contents($file);
        $this->assertTrue(strpos($contents, $msg) > 0);
    }

    function testFileNotExists()
    {
        $file = '/tmp/asdf1234' . md5(date(DATE_RFC822));
        $msg = 'unique new york';

        $l = new Octopus_Logger_File($file);
        $l->log($msg);

        $contents = file_get_contents($file);
        $this->assertTrue(strpos($contents, $msg) > 0);
        @unlink($file);
    }

    /**
     * @expectedException Octopus_Exception
     */
    function testOpenFileError()
    {
        $file = '/root/noperm';
        $msg = 'unique new york';
        // $this->expectOutputString("Can't open log file: $file.");

        $l = new Octopus_Logger_File($file);
        $l->log($msg);

        $r = @file_get_contents($file);
        $this->assertFalse($r);
    }

}
