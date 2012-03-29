<?php

class RestControllerTest extends Octopus_App_TestCase {

    function setUp() {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->app = $this->startApp();

        $this->createControllerFile(
            'api/1/apples',
            <<<END
<?php

class Api1ApplesController extends Octopus_Controller_Rest {

    public function postAction(\$name, \$color) {
        return \$this->success(array('id' => 2, 'from' => 'post'));
    }

    /**
     * @resourceRequired
     */
    public function getAction() {
        return \$this->success(array('id' => \$this->resource_id, 'name' => 'Big Apple', 'color' => 'green'));
    }

    /**
     * @resourceRequired
     */
    public function putAction(\$name, \$color, \$optional = '', \$truth = false) {
        if (\$name == 'foo' && \$color == 'green') {
            return \$this->success(array('id' => \$this->resource_id, 'from' => 'put', 'truth' => \$truth));
        }
    }

    /**
     * @resourceRequired
     */
    public function deleteAction() {
        return \$this->success(array('id' => \$this->resource_id, 'from' => 'delete'));
    }

}
END
        );

        $this->createControllerFile(
            'api/1/beets',
            <<<END
<?php

class Api1BeetsController extends Octopus_Controller_Rest {

    public function getAction() {
        return \$this->success(array('id' => \$this->resource_id, 'name' => 'Purple'));
    }

    public function postAction() {
        return \$this->success();
    }

    public function putAction(\$a, \$b = 4) {
        return compact('a', 'b');
    }

}
END
        );

        $this->createControllerFile(
            'api/1/additions',
            <<<END
<?php

class Api1AdditionsController extends Octopus_Controller_Rest {

    public function _before(\$action, \$args) {
        \$this->result = \$args['num'] + 1;
    }

    public function postAction(\$num) {
        return array('result' => \$this->result);
    }

    public function deleteAction(\$num, \$data) {
        return array('id' => \$this->resource_id, 'data' => \$data);
    }

}
END
        );

    }

    function testGetResource() {

        $resp = $this->app->getGetResponse('/api/1/apples/2');

        $this->assertEquals(
            array(
                'id' => 2,
                'name' => 'Big Apple',
                'color' => 'green',
            ),
            json_decode($resp->getContent(), true)
        );

        $this->assertEquals('application/json', $resp->contentType());
        $this->assertEquals(200, $resp->getStatus());

    }

    function testPostResource() {

        $args = array(
            'name' => 'foo',
            'color' => 'green',
        );
        $resp = $this->app->getPostResponse('/api/1/apples', $args);

        $this->assertEquals(
            array(
                'id' => 2,
                'from' => 'post',
            ),
            json_decode($resp->getContent(), true)
        );

        $this->assertEquals('application/json', $resp->contentType());
        $this->assertEquals(200, $resp->getStatus());

    }

    function testPutResource() {

        $args = array(
            'name' => 'foo',
            'color' => 'green',
        );
        $resp = $this->app->getPutResponse('/api/1/apples/2', $args);

        $this->assertEquals(
            array(
                'id' => 2,
                'from' => 'put',
                'truth' => false,
            ),
            json_decode($resp->getContent(), true)
        );

        $this->assertEquals('application/json', $resp->contentType());
        $this->assertEquals(200, $resp->getStatus());

    }

    function testDeleteResource() {

        $resp = $this->app->getDeleteResponse('/api/1/apples/2');

        $this->assertEquals(
            array(
                'id' => 2,
                'from' => 'delete',
            ),
            json_decode($resp->getContent(), true)
        );

        $this->assertEquals('application/json', $resp->contentType());
        $this->assertEquals(200, $resp->getStatus());

    }

    function testMissingMethod() {

        $resp = $this->app->getDeleteResponse('/api/1/beets', array('name' => 'fail'), true);

        $this->assertEquals(
            array(
                'errors' => array('Method not implemented'),
            ),
            json_decode($resp->getContent(), true)
        );

        $this->assertEquals('application/json', $resp->contentType());
        $this->assertEquals(405, $resp->getStatus());

    }

    function testMissingPost() {

        $resp = $this->app->getPostResponse('/api/1/apples', array('name' => 'fail'), true);

        $this->assertEquals(
            array(
                'errors' => array('color' => 'color is required.'),
            ),
            json_decode($resp->getContent(), true)
        );

        $this->assertEquals('application/json', $resp->contentType());
        $this->assertEquals(400, $resp->getStatus());

    }

    function testMissingGet() {

        $resp = $this->app->getResponse('/api/1/apples', true);

        $this->assertEquals(
            array(
                'errors' => array('resource' => 'resource id is required.'),
            ),
            json_decode($resp->getContent(), true)
        );

        $this->assertEquals('application/json', $resp->contentType());
        $this->assertEquals(400, $resp->getStatus());

    }

    function testMissingPut() {

        $resp = $this->app->getPutResponse('/api/1/apples', array('name' => 'fail'), true);

        $this->assertEquals(
            array(
                'errors' => array(
                    'resource' => 'resource id is required.',
                    'color' => 'color is required.',
                ),
            ),
            json_decode($resp->getContent(), true)
        );

        $this->assertEquals('application/json', $resp->contentType());
        $this->assertEquals(400, $resp->getStatus());

    }

    function testMissingDelete() {

        $resp = $this->app->getDeleteResponse('/api/1/apples', array(), true);

        $this->assertEquals(
            array(
                'errors' => array('resource' => 'resource id is required.'),
            ),
            json_decode($resp->getContent(), true)
        );

        $this->assertEquals('application/json', $resp->contentType());
        $this->assertEquals(400, $resp->getStatus());

    }

    function testBadMethod() {

        $_SERVER['REQUEST_METHOD'] = 'CRICKET';
        $resp = $this->app->getResponse('/api/1/beets', array('name' => 'fail'), true);

        $this->assertEquals(
            array(
                'errors' => array('Method not recognised'),
            ),
            json_decode($resp->getContent(), true)
        );

        $this->assertEquals('application/json', $resp->contentType());
        $this->assertEquals(501, $resp->getStatus());

    }

    function testBeforeArgsMethod() {

        $resp = $this->app->getPostResponse('/api/1/additions', array('num' => 2), true);

        $this->assertEquals(
            array(
                'result' => 3,
            ),
            json_decode($resp->getContent(), true)
        );

        $this->assertEquals('application/json', $resp->contentType());
        $this->assertEquals(200, $resp->getStatus());

    }

    function testPutExtraArg() {

        $args = array(
            'signed_request' => 'xxx',
            'name' => 'foo',
            'color' => 'green',
        );
        $resp = $this->app->getPutResponse('/api/1/apples/2', $args, true);

        $this->assertEquals(
            array(
                'id' => 2,
                'from' => 'put',
                'truth' => false,
            ),
            json_decode($resp->getContent(), true)
        );

        $this->assertEquals('application/json', $resp->contentType());
        $this->assertEquals(200, $resp->getStatus());

    }

    function testPutMissingOptional() {

        $args = array(
            'name' => 'foo',
            'color' => 'green',
            'truth' => true,
        );
        $resp = $this->app->getPutResponse('/api/1/apples/2', $args, true);

        $this->assertEquals(
            array(
                'id' => 2,
                'from' => 'put',
                'truth' => true,
            ),
            json_decode($resp->getContent(), true)
        );

        $this->assertEquals('application/json', $resp->contentType());
        $this->assertEquals(200, $resp->getStatus());

    }

    function testEmptyResult() {

        $resp = $this->app->getPostResponse('/api/1/beets', array(), true);

        $this->assertSame('', $resp->getContent());
        $this->assertEquals('application/json', $resp->contentType());
        $this->assertEquals(200, $resp->getStatus());

    }

    function testDefaultArguments() {
        $resp = $this->app->getPutResponse('/api/1/beets', array('a' => 2), true);

        $this->assertEquals(
            array(
                'a' => '2',
                'b' => '4',
            ),
            json_decode($resp->getContent(), true)
        );

        $this->assertEquals('application/json', $resp->contentType());
        $this->assertEquals(200, $resp->getStatus());
    }

    function testDeleteArguments() {

        $resp = $this->app->getDeleteResponse('/api/1/additions/22', array('num' => 4, 'data' => 'foo'), true);

        $this->assertEquals(
            array(
                'id' => 22,
                'data' => 'foo',
            ),
            json_decode($resp->getContent(), true)
        );

        $this->assertEquals('application/json', $resp->contentType());
        $this->assertEquals(200, $resp->getStatus());

    }

    /**
     * @zexpectedException PHPUnit_Framework_ExpectationFailedException
     */
    function testErrorStatus500() {

        $this->markTestSkipped('hard to test');

        $app = $this->startApp();

        $this->createControllerFile(
            'RestHasError',
            <<<END
<?php

class RestHasErrorController extends Octopus_Controller_Rest {

    public function getAction() {
        \$this->needs(1);
        return array('foo' => 'bar');
    }

    private function needs(\$a, \$b) {

    }

}

?>
END
        );

        $resp = $app->getResponse('/rest_has_error', true);
        $this->assertEquals(500, $resp->getStatus(), $resp->getContent());

    }

}
