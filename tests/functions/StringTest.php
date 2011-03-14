<?php

    /**
     * @group core
     */
    class StringTests extends PHPUnit_Framework_TestCase
    {
        function testEndIn() {
            
            $this->assertEquals(
                'foo/',
                end_in('/', 'foo'),
                'failed when needing to append'
            );
            
            $this->assertEquals(
                'foo/',
                end_in('/', 'foo/'),
                'failed when not needing to append'
            );
            
        }

        function testStartIn() {
                
            $this->assertEquals(
                '/foo',
                start_in('/', 'foo'),
                'failed when needing to prepend'
            );
            
            $this->assertEquals(
                '/foo',
                start_in('/', '/foo'),
                'failed when not needing to prepend'
            );
            
        }        
        
    }

?>
