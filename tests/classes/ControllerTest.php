<?php

Octopus::loadClass('Octopus_App_TestCase');

class ControllerTestController extends Octopus_Controller {

    public function test_redirect($url) {
        $this->redirect($url);
    }

    public function test_renderJson($data) {
        $this->renderJson($data);
    }

    public function test_renderJsonp($data, $callback = null) {
        if ($callback) {
            $this->renderJsonp($data, $callback);
        } else {
            $this->renderJsonp($data);
        }
    }

    public function test404() {
        $this->notFound('404view');
    }

}

class ControllerTest extends Octopus_App_TestCase {

    function test404() {

        $app = $this->startApp();
        $resp = new Octopus_Response(true);
        $controller = new ControllerTestController($app, $resp);

        $controller->__execute('test_404', array());
        $this->assertEquals('404view', $controller->view);

        $this->assertEquals(
            <<<END
HTTP/1.1 404 Not Found
END
            ,
            trim($resp)
        );

    }

    function testRedirectCancelable() {

        $app = $this->startApp();
        $resp = new Octopus_Response(true);
        $controller = new ControllerTestController($app, $resp);

        $controller->test_redirect('foo');
        $this->assertEquals(<<<END
HTTP/1.1 302 Found
Location: foo
END
            ,
            trim($resp)
        );

        cancel_redirects();

        $resp = new Octopus_Response(true);
        $controller = new ControllerTestController($app, $resp);
        $controller->test_redirect('foo');

        $resp = preg_replace('/-+/', '', trim($resp));
        $resp = preg_replace('/\s+/', ' ', $resp);
        $resp = preg_replace('/>\s+</', '><', $resp);
        $resp = trim($resp);

        $this->assertEquals(
            'HTTP/1.1 200 OK <div class="sgSquashedRedirectNotice"> Suppressed redirect to: <a href="foo"><strong>foo</strong></a></div>',
            $resp
        );
    }

    function testRenderJson() {

        $data = array(
            'foo' => 'bar',
            'active' => true
        );

        $app = $this->startApp();
        $resp = new Octopus_Response(true);
        $controller = new ControllerTestController($app, $resp);

        $controller->test_renderJson($data);

        $this->assertEquals(
            <<<END
HTTP/1.1 200 OK
Content-type: application/json

{"foo":"bar","active":true}
END
            ,
            trim($resp)
        );

    }

    function testRenderJsonp() {

        $data = array(
            'foo' => 'bar',
            'active' => true
        );

        $app = $this->startApp();
        $resp = new Octopus_Response(true);
        $controller = new ControllerTestController($app, $resp);

        $controller->test_renderJsonp($data, 'callbackFunc');

        $this->assertEquals(
            <<<END
HTTP/1.1 200 OK
Content-type: application/javascript

callbackFunc({"foo":"bar","active":true});
END
            ,
            trim($resp)
        );

    }

    function testRenderJsonpUsingCallbackFromGet() {

        global $_GET;

        $data = array(
            'foo' => 'bar',
            'active' => true
        );

        $app = $this->startApp();
        $resp = new Octopus_Response(true);
        $controller = new ControllerTestController($app, $resp);

        $_GET['callback'] = 'callbackFuncFromGet';

        $controller->test_renderJsonp($data);

        $this->assertEquals(
            <<<END
HTTP/1.1 200 OK
Content-type: application/javascript

callbackFuncFromGet({"foo":"bar","active":true});
END
            ,
            trim($resp)
        );

    }

}

?>
