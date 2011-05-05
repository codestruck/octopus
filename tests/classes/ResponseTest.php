<?php

class ResponseTest extends PHPUnit_Framework_TestCase {

    function testContinueProcessing() {

        $tests = array(
            200 => true,
            301 => false,
            302 => false,
            307 => false,
            400 => true,
            401 => true,
            402 => true,
            403 => true,
            500 => true
        );

        foreach($tests as $status => $continue) {

            $r = new Octopus_Response();
            $r->setStatus($status);

            $this->assertEquals($continue, $r->shouldContinueProcessing(), $status . ' ' . ($continue ? 'should continue' : 'should not continue'));
        }

    }

    function testTempRedirect() {

        $r = new Octopus_Response();
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

        $r = new Octopus_Response();
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

    function testPermanentRedirect() {

        $r = new Octopus_Response();
        $r->redirect('some/path', true);

        $this->assertEquals(
            <<<END
HTTP/1.1 301 Moved Permanently
Location: some/path


END
            ,
            $r->__toString()
        );

        $r = new Octopus_Response();
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
