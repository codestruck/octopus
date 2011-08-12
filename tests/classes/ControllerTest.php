<?php

Octopus::loadClass('Octopus_App_TestCase');

class ControllerTestController extends Octopus_Controller {

    public function do_redirect($url) {
        $this->redirect($url);
    }

    public function do_renderJson($data) {
        $this->renderJson($data);
    }

    public function do_renderJsonp($data, $callback = null) {
        if ($callback) {
            $this->renderJsonp($data, $callback);
        } else {
            $this->renderJsonp($data);
        }
    }

    public function do_404() {
        $this->notFound('404view');
    }

}

class ControllerTest extends Octopus_App_TestCase {

    function dontTest404() {

        $app = $this->startApp();
        $resp = new Octopus_Response(true);
        $controller = new ControllerTestController($app, new Octopus_Request($app, ''), $resp);

        $controller->__execute('do_404', array());
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
        $controller = new ControllerTestController($app, new Octopus_Request($app, ''), $resp);

        uncancel_redirects();
        $controller->do_redirect('foo');
        $this->assertEquals(<<<END
HTTP/1.1 302 Found
Location: foo
END
            ,
            trim($resp)
        );

        cancel_redirects();

        $resp = new Octopus_Response(true);
        $controller = new ControllerTestController($app, new Octopus_Request($app, ''), $resp);
        $controller->do_redirect('foo');

        $resp = preg_replace('/-+/', '', trim($resp));
        $resp = preg_replace('/\s+/', ' ', $resp);
        $resp = preg_replace('/>\s+</', '><', $resp);
        $resp = trim($resp);

        $this->assertTrue(
            !!preg_match('#HTTP/1.1 200 OK.*Suppressed redirect#im', trim($resp)),
            '<< ' . trim($resp) . ' >>'
        );
    }

    function dontTestRenderJson() {

        $data = array(
            'foo' => 'bar',
            'active' => true
        );

        $app = $this->startApp();
        $resp = new Octopus_Response(true);
        $controller = new ControllerTestController($app, new Octopus_Request($app, ''), $resp);

        $controller->do_renderJson($data);

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

    function dontTestRenderJsonp() {

        $data = array(
            'foo' => 'bar',
            'active' => true
        );

        $app = $this->startApp();
        $resp = new Octopus_Response(true);
        $controller = new ControllerTestController($app, new Octopus_Request($app, ''), $resp);

        $controller->do_renderJsonp($data, 'callbackFunc');

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

    function dontTestRenderJsonpUsingCallbackFromGet() {

        global $_GET;

        $data = array(
            'foo' => 'bar',
            'active' => true
        );

        $app = $this->startApp();
        $resp = new Octopus_Response(true);
        $controller = new ControllerTestController($app, new Octopus_Request($app, ''), $resp);

        $_GET['callback'] = 'callbackFuncFromGet';

        $controller->do_renderJsonp($data);

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
