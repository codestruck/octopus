<?php

Octopus::loadClass('Octopus_App_TestCase');

class ControllerTestController extends Octopus_Controller {

    public function test_redirect($url) {
        $this->redirect($url);
    }

}

class ControllerTest extends Octopus_App_TestCase {

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

}

?>
