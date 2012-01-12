<?php

class TestApiController extends Octopus_Controller_Api {

    var $protect = array('protectedAddMember');

    public function addMember($name, $password, $favoriteColor = 'blue') {
        return compact('name', 'password', 'favoriteColor');
    }

    public function protectedAddMember($name, $password, $favoriteColor = 'blue') {
        return compact('name', 'password', 'favoriteColor');
    }

}

class ApiControllerTest extends Octopus_App_TestCase {

    function testSuccessfulCall() {

        $app = $this->startApp();
        $resp = new Octopus_Response(true);
        $controller = new TestApiController($app, new Octopus_Request($app,''), $resp);

        $args = array('name' => 'Matt', 'password' => 'test', 'favoriteColor' => 'green');
        $data = $controller->__execute('add-member', $args);

        $this->assertEquals($args, $data);
    }

    function testMissingArgsCall() {

        $app = $this->startApp();

        foreach(array('name', 'password') as $missingArg) {

            $args = array('name' => 'Matt', 'password' => 'test', 'favoriteColor' => 'green');
            unset($args[$missingArg]);

            $resp = new Octopus_Response(true);
            $controller = new TestApiController($app, new Octopus_Request($app,''), $resp);

            $data = $controller->__execute('add-member', $args);

            $this->assertEquals(
                array('success' => false, 'errors' => array($missingArg => "$missingArg is required.")),
                $data
            );

        }

    }

    function testContentType() {

        $app = $this->startApp();

        $this->createControllerFile(
            'api/1/TestContentType',
            <<<END
<?php

class Api1TestContentTypeController extends Octopus_Controller_Api {

    public function test() {
        return array('foo' => 'bar');
    }

}

?>
END
        );

        $resp = $app->getResponse('/api/1/test_content_type/test', true);
        $this->assertEquals('application/json', $resp->contentType());

    }


    function testError() {

        $app = $this->startApp();

        $this->createControllerFile(
            'api/1/TestError',
            <<<END
<?php

class Api1TestErrorController extends Octopus_Controller_Api {

    public function testSingleError() {
        return \$this->error('A single error');
    }

    public function testMultipleErrors() {
    	return \$this->error(array('error 1', 'error 2'));
    }
}

?>
END
        );

        $resp = $app->getResponse('/api/1/test-error/test-single-error', true);
        $this->assertEquals('application/json', $resp->contentType());
        $this->assertEquals(403, $resp->getStatus(), 'Status code is 403');
        $this->assertEquals(
        	array(
        		'success' => false,
        		'errors' => array('A single error')
	        ),
	        json_decode($resp->getContent(), true)
	    );

	    $resp = $app->getResponse('/api/1/test-error/test-multiple-errors', true);
	    $this->assertEquals('application/json', $resp->contentType());
	    $this->assertEquals(403, $resp->getStatus());
	    $this->assertEquals(
	    	array(
		    	'success' => false,
		    	'errors' => array('error 1', 'error 2')
		    ),
		    json_decode($resp->getContent(), true)
		);

    }

    function testErrorInBefore() {

		$app = $this->startApp();

        $this->createControllerFile(
            'api/1/TestErrorInBefore',
            <<<END
<?php

class Api1TestErrorInBeforeController extends Octopus_Controller_Api {

	public function _before(\$action, \$args) {
		return \$this->error('_before fails');
	}

	public function test() {

	}
}

?>
END
        );

        $resp = $app->getResponse('/api/1/test-error-in-before/test', true);
        $this->assertEquals(403, $resp->getStatus(), 'status is 403');
        $this->assertEquals('application/json', $resp->contentType(), 'content type is application/json');
        $this->assertEquals(
        	array('success' => false, 'errors' => array('_before fails')),
        	json_decode($resp->getContent(), true)
	    );

    }

    function testCustomErrorStatusCode() {

		$app = $this->startApp();

        $this->createControllerFile(
            'api/1/TestCustomErrorStatusCode',
            <<<END
<?php

class Api1TestCustomErrorStatusCodeController extends Octopus_Controller_Api {

	public function test() {
		return \$this->error('not found', 404);
	}
}

?>
END
        );

        $r = $app->getResponse('/api/1/test-custom-error-status-code/test', true);
        $this->assertEquals(404, $r->getStatus());
        $this->assertEquals(
        	array('success' => false, 'errors' => array('not found')),
        	json_decode($r->getContent(), true)
	    );


    }

    function testDataInError() {

		$app = $this->startApp();

        $this->createControllerFile(
            'api/1/TestDataInError',
            <<<END
<?php

class Api1TestDataInErrorController extends Octopus_Controller_Api {

	public function test() {
		return \$this->error('not found', array('foo' => 'bar'));
	}
}

?>
END
        );

        $r = $app->getResponse('/api/1/test-data-in-error/test', true);
        $this->assertEquals(403, $r->getStatus());
        $this->assertEquals(
        	array('success' => false, 'data' => array('foo' => 'bar'), 'errors' => array('not found')),
        	json_decode($r->getContent(), true)
	    );


    }

}
