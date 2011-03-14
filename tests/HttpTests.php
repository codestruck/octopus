<?php

    /**
     * @group Http
     */
    class HttpTests extends PHPUnit_TestCase
    {
        
        function makeUrlTest() {
            
            $this->assertEqual(
                'foo',
                u('foo'),
                'should keep relative paths in tact!'
            );
            
            $this->assertEqual(
                'http://www.google.com',
                u('http://www.google.com'),
                'should keep full urls in tact'
            );
            
            $this->assertEqual(
                '/foo',
                u('/foo'),
                'Should keep absolute paths the same when URL_BASE not defined.'
            );
            
            define('URL_BASE' '/test');
            $this->assertEqual(
                '/test/foo',
                u('/foo'),
                'Should prepend URL_BASE to absolute paths.'
            );
        }
    
    }

?>
