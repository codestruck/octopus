<?php

SG::loadClass('SG_Router');

/**
 * @group router
 *
 */
class SG_Router_Test extends PHPUnit_Framework_TestCase
{

    function setUp() {
        
        
        @mkdir("routertest");
        touch("routertest/foo.php");
        touch("routertest/bar.php");
        
        @mkdir("routertest/subdir");
        touch("routertest/subdir/index.php");
        touch("routertest/subdir/foo.php");

    }
    
    function tearDown() {
        
        exec('rm -rf routertest');
        
    }
   
    function testAddDirectory() {

        
        $r = new SG_Router();
        $r->addDirectory("routertest");
        
        $this->assertEquals(
            "routertest/foo.php",
            $r->resolve("foo")
        );
        
        $this->assertEquals(
            "routertest/subdir/index.php",
            $r->resolve("subdir")
        );
        
        $this->assertEquals(
            "routertest/subdir/index.php",
            $r->resolve("subdir/")
        );
        
        $this->assertEquals(
            "routertest/subdir/foo.php",
            $r->resolve("subdir/foo")
        );
        
        // sometimes, if a file exists, apache adds the extension to the end
        $this->assertEquals(
            "routertest/foo.php",
            $r->resolve("foo.php")
        );
        
        $this->assertEquals(
            "routertest/subdir/foo.php",
            $r->resolve("subdir/foo.php")
        );
        

    }
    
    function testAddDirectoryWithPrefix() {
        
        $r = new SG_Router();
        $r->addDirectory('routertest', 'test');
        
        $this->assertEquals(
            "routertest/foo.php",
            $r->resolve("test/foo")
        );
        
        $this->assertEquals(
            "routertest/subdir/index.php",
            $r->resolve("test/subdir")
        );
        
        $this->assertEquals(
            "routertest/subdir/index.php",
            $r->resolve("test/subdir/")
        );
        
        $this->assertEquals(
            "routertest/subdir/foo.php",
            $r->resolve("test/subdir/foo")
        );
        
        // sometimes, if a file exists, apache adds the extension to the end
        $this->assertEquals(
            "routertest/foo.php",
            $r->resolve("test/foo.php")
        );
        
        $this->assertEquals(
            "routertest/subdir/foo.php",
            $r->resolve("test/subdir/foo.php")
        );
        
    }
    
    function testAddVirtual() {
     
        $r = new SG_Router();
        
        $r->add('/.*/', 'my/virtual/page');
        
        $this->assertEquals(
            "my/virtual/page",
            $r->resolve("foo")
        );
        
        $this->assertEquals(
            "my/virtual/page",
            $r->resolve("foo/bar/baz")
        );
        
    }
    
    function testAddVirtualWithMatchReferences() {
        
        $r = new SG_Router();
        $r->add('-^friends/(.*?)/(.*)-i', 'pages/friends/$1/$2.html');
        
        $this->assertEquals(
            'pages/friends/joe/photos.html',
            $r->resolve('friends/joe/photos')
        );
        
    }


}
