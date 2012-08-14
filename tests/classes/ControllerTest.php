<?php

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class ControllerTest extends Octopus_App_TestCase {

    function testRedirectCancelable() {

        $app = $this->startApp();
        $req = $app->createRequest('controller-test/do-redirect');
        $resp = new Octopus_Response($req);
        $controller = new ControllerTestController($app, $req, $resp);

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
        Octopus_Log::reset(); // prevent dump_r message because of canceled redirect

        $resp = new Octopus_Response($req);
        $controller = new ControllerTestController($app, new Octopus_Request($app, ''), $resp);
        $controller->do_redirect('foo');

        $this->assertEquals(418, $resp->getStatus()); // I'm a teapot
    }

    function dontTestRenderJson() {

        $data = array(
            'foo' => 'bar',
            'active' => true
        );

        $app = $this->startApp();
        $resp = new Octopus_Response();
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
        $resp = new Octopus_Response();
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
        $resp = new Octopus_Response();
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

    /**
     * @zexpectedException PHPUnit_Framework_ExpectationFailedException
     */
    function testErrorStatus500() {

        $this->markTestSkipped('hard to test');

        $app = $this->startApp();

        $this->createControllerFile(
            'HasError',
            <<<END
<?php

class HasErrorController extends Octopus_Controller {

    public function test() {
        \$this->needs(1);
        return array('foo' => 'bar');
    }

    private function needs(\$a, \$b) {

    }

}

?>
END
        );
        $this->createViewFile('has_error/test.tpl');

        $resp = $app->getResponse('/has_error/test', true);
        $this->assertEquals(500, $resp->getStatus());

    }

}

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
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
