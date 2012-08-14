<?php
/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class ResponseTest extends Octopus_App_TestCase {

    function createResponse($path = '/foo') {

        $app = $this->getApp();
        $request = ($path instanceof Octopus_Request) ? $path : $app->createRequest($path);
        return new Octopus_Response($request);

    }

    function testSetViewData() {

        $app = $this->startApp();
        $resp = $app->getResponse('/foo');

        set_view_data(__METHOD__, 'hi');
        $this->assertEquals('hi', $resp[__METHOD__]);

    }

    function testGetViewData() {

        $app = $this->startApp();
        $resp = $app->getResponse('/foo');

        $resp->set(__METHOD__, 'blerrrrg');

        $this->assertEquals('blerrrrg', get_view_data(__METHOD__));

        $resp->set('foo', 'bar');
        $data = get_view_data();

        $this->assertEquals(
            array(
                __METHOD__ => 'blerrrrg',
                'foo' => 'bar',
            ),
            $data,
            'get_view_data() returns all data attached to response'
        );

    }

    function testAppendContent() {

        $resp = $this->createResponse('/foo');
        $resp->append("foo");
        $resp->append("bar");

        $this->assertEquals("foo\nbar", $resp->render(true));

    }

    /**
     * @expectedException Octopus_Exception
     */
    function testAppendContentFailsWhenStopped() {

        $resp = $this->createResponse('/foo');
        $resp->stop();
        $resp->append('hi');

    }

    function testRenderViewSpecified() {

        $this->createViewFile('blerg', 'Testing 1 2 3');
        $resp = $this->createResponse('/foo');

        $resp->view = 'blerg';
        $this->assertEquals('Testing 1 2 3', $resp->render(true));

    }

    function testRenderLayoutSpecified() {

        $app = $this->startApp();
        $this->assertSame($app, $this->getApp());
        $siteDir = $this->getSiteDir();
        $layoutDir = $siteDir . 'themes/default/layouts/';
        mkdir($layoutDir, 0777, true);

        file_put_contents($layoutDir . 'alternate_layout.tpl', 'your alternate layout');

        $resp = $this->createResponse('/foo');
        $resp->theme = 'default';
        $resp->layout = 'alternate_layout';
        $this->assertEquals('your alternate layout', $resp->render(true));

    }

    function testTheme() {

        $app = $this->startApp();
        $settings = $app->getSettings();

        $settings->reset('site.theme');

        $resp = $app->getResponse('/foo');
        $this->assertEquals('default', $resp->getTheme());

        $settings->set('site.theme', 'foo');
        $this->assertEquals('foo', $resp->getTheme());

        $resp = $app->getResponse('/admin');
        $this->assertEquals('foo', $resp->getTheme());

        $settings->set('site.theme.admin', 'bar');

        $this->assertEquals('bar', $resp->getTheme());
        $this->assertEquals('bar', $resp->getTheme());
        $this->assertEquals('bar', $resp->getTheme());

        $resp->setTheme('baz');
        $this->assertEquals('baz', $resp->theme);
        $this->assertEquals('baz', $resp->getTheme());

        $resp = $app->getResponse('/foo');
        $this->assertEquals('foo', $resp->getTheme());

    }


    function testTemplateRendererByDefault() {

        $resp = $this->createResponse();
        $this->assertTrue($resp->renderer instanceof Octopus_Renderer_Template);

    }

    function testJsonRendererForJsonContentType() {

        $resp = $this->createResponse();
        $resp->contentType = 'application/json';

        $this->assertTrue($resp->renderer instanceof Octopus_Renderer_Json);

    }

    function testRequestProperty() {

        $app = $this->getApp();
        $request = $app->createRequest('/foo');

        $response = $this->createResponse($request);
        $this->assertSame($request, $response->request);
        $this->assertSame($request, $response->getRequest());

    }

    function testValuesAsProperty(){

        $r = $this->createResponse();
        $r->set(array('foo' => 'bar', 'baz' => 'bat'));
        $this->assertEquals(
            array('foo' => 'bar', 'baz' => 'bat'),
            $r->values
        );

        $r->clear('baz');
        $this->assertEquals(array('foo' => 'bar'), $r->values);

    }

    /**
     * @dataProvider provideMethodsAndTestData
     * @expectedException Octopus_Exception
     */
    function testAlterAfterStopThrowsException($method, $args = array()) {

        if (!is_array($args)) $args = array($args);

        $r = $this->createResponse();
        $r->stop();
        call_user_func_array(array($r, $method), $args);

    }

    function provideMethodsAndTestData() {

        return array(

            array('clear', 'foo'),
            array('clearHeader', 'X-some-header'),
            array('clearValues'),
            array('forbidden'),
            array('redirect', '/foo/bar'),
            array('reset'),
            array('resetHeaders'),
            array('set', array('foo', 'bar')),
            array('setHeader', array('X-some-header', 42)),
            array('setStatus', 404),
            array('setTheme', 'foo'),
            array('setView', 'foo'),

        );

    }


    function testSetAndGet() {

        $r = $this->createResponse();

        $r->set('foo', 'bar');
        $this->assertEquals('bar', $r->get('foo'));

        $r->set(array(
            'mammal' => 'cat',
            'reptile' => 'lizard',
        ));
        $this->assertEquals('cat', $r->get('mammal'));
        $this->assertEquals('lizard', $r->get('reptile'));
        $this->assertEquals('bar', $r->get('foo'));

        $this->assertEquals(
            array(
                'foo' => 'bar',
                'mammal' => 'cat',
                'reptile' => 'lizard',
            ),
            $r->getValues()
        );

        $r->clear('foo', 'reptile');
        $this->assertEquals(array('mammal' => 'cat'), $r->getValues());

        $r->clearValues();
        $this->assertEquals(array(), $r->getValues());

        $r->set(array('foo' => 'bar', 'baz' => 'bat'));
        $r->clear(array('foo', 'baz'));
        $this->assertEquals(array(), $r->getValues());

    }

    function testSetAndGetAsArray() {

        $r = $this->createResponse();
        $r['foo'] = 'bar';
        $this->assertTrue(isset($r['foo']), 'isset is true');
        $this->assertEquals('bar', $r['foo']);

        unset($r['foo']);
        $this->assertFalse(isset($r['foo']));

        $this->assertEquals(array(), $r->getValues());

    }

    function testContentType() {

        $r = $this->createResponse();
        $r->setHeader('Content-type', 'text/html');

        $this->assertEquals('text/html', $r->getHeader('Content-type'));
        $this->assertEquals('text/html', $r->getHeader('CONTENT-TYPE'));
        $this->assertEquals('text/html', $r->contentType);

                $this->assertEquals(
                    <<<END
HTTP/1.1 200 OK
Content-type: text/html
END
                    ,
                    (string)$r
                );


        $r->contentType = 'text/plain';
        $this->assertEquals('text/plain', $r->getHeader('Content-type'));
        $this->assertEquals('text/plain', $r->getHeader('CONTENT-TYPE'));
        $this->assertEquals('text/plain', $r->contentType);

                $this->assertEquals(
                    <<<END
HTTP/1.1 200 OK
Content-type: text/plain
END
                    ,
                    (string)$r
                );

    }

    function testStatus() {

        $r = $this->createResponse();
        $this->assertEquals(200, $r->status, 'status defaults to 200');

        $r->status = 404;
        $this->assertEquals(404, $r->status);
        $this->assertEquals('HTTP/1.1 404 Not Found', $r->getStatusString());

    }

    function testCharset() {

        $r = $this->createResponse();
        $this->assertEquals('UTF-8', $r->charset, 'charset defaults to utf-8');

        $r->charset = 'foo';
        $this->assertEquals('foo', $r->charset);


        $this->assertEquals(
            <<<END
HTTP/1.1 200 OK
Content-type: text/html; charset=foo
END
            ,
            (string)$r
        );

    }

    function testIsHtml() {

        $r = $this->createResponse();
        $this->assertTrue($r->isHtml(), 'isHtml() is true by default');

        $r->setContentType('text/plain');
        $this->assertFalse($r->isHtml(), 'isHtml() is false for text/plain');

        $r->setContentType('text/html');
        $this->assertTrue($r->isHtml(), 'isHtml() is true for text/html');

    }

    function testView() {

        $r = $this->createResponse();
        $this->assertEquals('', $r->view);

        $r->view = 'foo/bar';
        $this->assertEquals('foo/bar', $r->view);

    }

    function testTempRedirect() {

        $r = $this->createResponse();
        $this->assertTrue($r->active);

        $r->redirect('some/path');

        $this->assertEquals(
            <<<END
HTTP/1.1 302 Found
Location: some/path
END
            ,
            $r->__toString()
        );

        $this->assertFalse($r->active, 'should stop processing.');

        $r = $this->createResponse();
        $r->setHeader('X-Some-Header', 42);

        $r->redirect('some/path');
        $this->assertEquals(
            <<<END
HTTP/1.1 302 Found
Location: some/path
END
            ,
            $r->__toString()
        );

        $this->assertFalse($r->active, 'should stop processing');
    }

    function testPermanentRedirect() {

        $r = $this->createResponse();
        $r->redirect('some/path', true);

        $this->assertEquals(
            <<<END
HTTP/1.1 301 Moved Permanently
Location: some/path
END
            ,
            $r->__toString()
        );

        $r = $this->createResponse();
        $r->setHeader('X-Some-Header', 42);

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

    function testStop() {

        $r = $this->createResponse();
        $r->stop();
        $this->assertFalse($r->active);

    }



}
