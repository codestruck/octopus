<?php

    /**
     * @group core
     */
    class HttpTests extends PHPUnit_Framework_TestCase
    {
        
        function testMakeUrl() {
            
            $this->assertEquals(
                'foo',
                u('foo'),
                'should keep relative paths in tact!'
            );
            
            $this->assertEquals(
                'http://www.google.com',
                u('http://www.google.com'),
                'should keep full urls in tact'
            );
            
            $this->assertEquals(
                '/test/foo',
                u('/foo', null, array('URL_BASE' => '/test/')),
                'Should prepend URL_BASE to absolute paths.'
            );
            
        }
        
        function testFindUrlBase() {
            
            $this->assertEquals(
                '/',
                find_url_base('/var/www/', '/var/www/'),
                'when doc_root == root_dir, base should be slash'
            );
            
            $this->assertEquals(
                '/subdir/',
                find_url_base('/var/www/subdir/', '/var/www/'),
                'should detect when running in subdir.'
            );
            
            $this->assertEquals(
                false,
                find_url_base('/var/www/', '/some/weird/path/'),
                'Should return false when not running off document_root'
            );
                
            
        }
    
    }

?>
