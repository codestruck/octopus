<?php

class ResponseTest extends PHPUnit_Framework_TestCase {

    function testTempRedirect() {

        $r = new Octopus_Response(true);
        $r->redirect('some/path');

        $this->assertEquals(
            <<<END
HTTP/1.1 302 Found
Location: some/path


END
            ,
            $r->__toString()
        );

        $this->assertFalse($r->shouldContinueProcessing(), 'should stop processing.');

        $r = new Octopus_Response(true);
        $r->addHeader('X-Some-Header', 42);
        $r->append("Here is some fun content!");

        $r->redirect('some/path');
        $this->assertEquals(
            <<<END
HTTP/1.1 302 Found
Location: some/path


END
            ,
            $r->__toString()
        );

        $this->assertFalse($r->shouldContinueProcessing(), 'should stop processing');
    }

    function testContentType() {
        
        $r = new Octopus_Response(true);
        $this->assertEquals('text/html', $r->contentType(), 'default to text/html content type');

        $r->contentType('application/json');
        $this->assertEquals('application/json', $r->contentType());

    }

    function testPermanentRedirect() {

        $r = new Octopus_Response(true);
        $r->redirect('some/path', true);

        $this->assertEquals(
            <<<END
HTTP/1.1 301 Moved Permanently
Location: some/path


END
            ,
            $r->__toString()
        );

        $r = new Octopus_Response(true);
        $r->addHeader('X-Some-Header', 42);
        $r->append("Here is some fun content!");

        $r->redirect('some/path', true);
        $this->assertEquals(
            <<<END
HTTP/1.1 301 Moved Permanently
Location: some/path


END
            ,
            $r->__toString()
        );

    }

}

?>
