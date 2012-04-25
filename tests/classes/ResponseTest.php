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

    function testStop() {

        $r = new Octopus_Response(true);
        $r->contentType('application/json');
        $r->append(json_encode(true));
        $r->stop();

        $this->assertFalse($r->shouldContinueProcessing());

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

    function testIsHtml() {

    	$r = new Octopus_Response(true);
    	$this->assertTrue($r->isHtml(), 'isHtml() is true by default');

    	$r->contentType('text/plain');
    	$this->assertFalse($r->isHtml(), 'isHtml() is false for text/plain');

    	$r->contentType('text/html;charset=UTF-8');
    	$this->assertTrue($r->isHtml(), 'isHtml() is true for text/html with charset');

    }

    function testGetSetContentType() {

    	$r = new Octopus_Response(true);

    	$this->assertEquals('text/html', $r->contentType());
    	$this->assertEquals('text/html', $r->getContentType());

    	$r->contentType('text/plain');
    	$this->assertEquals('text/plain', $r->contentType());
    	$this->assertEquals('text/plain', $r->getContentType());

    	$r->setContentType('application/json');
    	$this->assertEquals('application/json', $r->contentType());
    	$this->assertEquals('application/json', $r->getContentType());

    }

    function testTurnBufferingOffFlushes() {

    	$r = new Octopus_Response(true);
    	$r->append('foo');

    	ob_start();
    	$r->buffer(false);
    	$content = ob_get_clean();

    	$this->assertEquals('foo', $content, 'response was flushed when buffer turned off');

    }


}

?>
