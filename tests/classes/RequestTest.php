<?php
/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class RequestTest extends Octopus_App_TestCase {

    function testPreservePathTrailingSlash() {

        $app = $this->startApp();
        $this->createControllerFile('PreserveSlash');

        $tests = array(
            '/preserve-slash' => 'preserve-slash',
            '/preserve-slash/' => 'preserve-slash/'
        );

        foreach($tests as $input => $expected) {

            $req = $app->createRequest($input);
            $this->assertEquals($expected, $req->getPath());

        }

    }

    function testDontAllowPathMonkeyBusiness() {

        $app = $this->startApp();

        // test basic path sanity stuff

        $tests = array(
            '../../../../etc/passwd' => 'etc/passwd',
            'parent/child1/../child2' => 'parent/child2',
            'parent/./child//grandchild' => 'parent/child/grandchild'
        );
        foreach($tests as $input => $expected) {
            $req = $app->createRequest($input);
            $this->assertEquals($expected, $req->getPath(), "getPath failed on '$input'");
            $this->assertEquals($expected, $req->getResolvedPath(), "getResolvedPath failed on '$input'");
        }

    }

    function testFindController() {

        $app = $this->startApp();

        $controllerFile = $this->createControllerFile('FindController');

        $tests = array(
            /*
           '/find-controller' => array('file' => $controllerFile, 'potential_names' => array('Find_ControllerController', 'FindControllerController'), 'action' => 'index', 'original_action' => '', 'args' => array()),
           '/find-controller/index/foo/bar' => array('file' => $controllerFile, 'potential_names' => array('Find_ControllerController', 'FindControllerController'), 'action' => 'index', 'original_action' => 'index', 'args' => array('foo', 'bar')),
           '/find-controller/index' => array('file' => $controllerFile, 'potential_names' => array('Find_ControllerController', 'FindControllerController'), 'action' => 'index', 'original_action' => 'index', 'args' => array()),
           '/find-controller/view/57' => array('file' => $controllerFile, 'potential_names' => array('Find_ControllerController', 'FindControllerController'), 'action' => 'view', 'original_action' => 'view', 'args' => array(57)),
           */
           '/find-controller/view//57/andedit' => array('file' => $controllerFile, 'potential_names' => array('Find_ControllerController', 'Find_Controller_Controller', 'FindControllerController'), 'action' => 'view', 'original_action' => 'view', 'args' => array(57, 'andedit')),
        );

        foreach($tests as $path => $expected) {

            $req = $app->createRequest($path);
            $this->assertControllerInfoMatches($expected, $req);

        }

    }

    function testFindMostSpecificController() {

        $app = $this->startApp();
        $baseFile = $this->createControllerFile('MostSpecific');
        $childFile = $this->createControllerFile('MostSpecific_Child');

        $tests = array(
            '/most-specific' => $baseFile,
            '/most-specific/index' => $baseFile,
            '/most-specific/child' => $childFile,
            '/most-specific/child/index' => $childFile
        );

        foreach($tests as $path => $expected) {

            $req = $app->createRequest($path);
            $this->assertControllerInfoMatches($expected, $req);

        }
    }

    function testFindControllerInSubdir() {

        $app = $this->startApp();

        $controllerFile = $this->createControllerFile('api/1/Deep');

        $tests = array(
           '/api/1/deep' => array('file' => $controllerFile, 'potential_names' => array('Api_1_DeepController', 'Api_1_Deep_Controller', 'Api1DeepController', 'DeepController'), 'action' => 'index', 'original_action' => '', 'args' => array()),
           '/api/1/deep/index' => array('file' => $controllerFile, 'potential_names' => array('Api_1_DeepController', 'Api_1_Deep_Controller', 'Api1DeepController', 'DeepController'), 'action' => 'index', 'original_action' => 'index', 'args' => array()),
           '/api/1/deep/view/57' => array('file' => $controllerFile, 'potential_names' => array('Api_1_DeepController', 'Api_1_Deep_Controller', 'Api1DeepController', 'DeepController'), 'action' => 'view', 'original_action' => 'view', 'args' => array(57)),
           '/api/1/deep/view/57/something' => array('file' => $controllerFile, 'potential_names' => array('Api_1_DeepController', 'Api_1_Deep_Controller', 'Api1DeepController', 'DeepController'), 'action' => 'view', 'original_action' => 'view', 'args' => array(57, 'something')),
        );

        foreach($tests as $path => $expected) {

            $req = $app->createRequest($path);
            $this->assertControllerInfoMatches($expected, $req);

        }

    }

    function testFindDashedControllers() {

        $app = $this->startApp();

        $controllerFile = $app->getOption('SITE_DIR') . 'controllers/DashDash.php';
        touch($controllerFile);

        $tests = array(
           '/dash-dash' => array('file' => $controllerFile, 'potential_names' => array('Dash_DashController', 'Dash_Dash_Controller', 'DashDashController'), 'action' => 'index', 'original_action' => '', 'args' => array()),
           '/dash-dash/index' => array('file' => $controllerFile, 'potential_names' => array('Dash_DashController', 'Dash_Dash_Controller', 'DashDashController'), 'action' => 'index', 'original_action' => 'index', 'args' => array()),
           '/dash-dash/view/57' => array('file' => $controllerFile, 'potential_names' => array('Dash_DashController', 'Dash_Dash_Controller', 'DashDashController'), 'action' => 'view', 'original_action' => 'view', 'args' => array(57)),
           '/dash-dash/view/andedit/57' => array('file' => $controllerFile, 'potential_names' => array('Dash_DashController', 'Dash_Dash_Controller', 'DashDashController'), 'action' => 'view', 'original_action' => 'view', 'args' => array('andedit', 57)),
        );

        foreach($tests as $path => $expected) {

            $req = $app->createRequest($path);
            $this->assertControllerInfoMatches($expected, $req);

        }

    }

    function testInvalidActionBecomesArg() {

        $app = $this->startApp();

        $controllerFile = $this->createControllerFile('1/InvalidActionBecomesArg');

        $tests = array(
           '/1/invalid-action-becomes-arg/57' => array('file' => $controllerFile, 'action' => 'index', 'args' => array(57)),
           '/1/invalid-action-becomes-arg/index/57' => array('file' => $controllerFile, 'action' => 'index', 'args' => array(57)),
           '/1/invalid-action-becomes-arg/57/edit/90' => array('file' => $controllerFile, 'action' => 'edit', 'args' => array(57, 90)),
           '/1/invalid-action-becomes-arg/remove/57/90' => array('file' => $controllerFile, 'action' => 'remove', 'args' => array(57, 90))
        );

        foreach($tests as $path => $expected) {
            $req = $app->createRequest($path);
            $this->assertControllerInfoMatches($expected, $req);
        }

    }

    function testFindApiControllers() {

        $app = $this->startApp();

        $controllerFile = $this->createControllerFile('api/1/FindApiTest');

        $tests = array(
            '/api/1/find-api-test/57' => array('file' => $controllerFile, 'action' => 'index', 'args' => array(57)),
           '/api/1/find-api-test/view/57' => array('file' => $controllerFile, 'action' => 'view', 'args' => array(57)),
        );

        foreach($tests as $path => $expected) {
            $req = $app->createRequest($path);
            $this->assertControllerInfoMatches($expected, $req);
            $this->assertEquals($expected['action'], $req->getAction());
        }

    }

    function testAlreadySetController() {

        $app = $this->startApp();

        $controllerFile = $this->createControllerFile('api/1/SavedTest');
        $request = $app->createRequest('/api/1/saved-test');
        $controller = $request->getController();

        $this->assertTrue($request->getController() instanceof SavedTestController);

        $this->assertEquals($controllerFile, $request->getControllerFile());

    }

    function testDefaultController() {

        $app = $this->startApp();
        $req = $app->createRequest('/api/1/not_exist');
        $this->assertTrue($req->getController() instanceof DefaultController);
        $this->assertTrue($req->isDefaultController());

    }

    function testMethod() {
        $app = $this->startApp();

        unset($_SERVER['REQUEST_METHOD']);
        $request = $app->createRequest('/');
        $this->assertEquals('get', $request->getMethod());
        $this->assertTrue($request->isGet());

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $request = $app->createRequest('/');
        $this->assertEquals('get', $request->getMethod());
        $this->assertTrue($request->isGet());

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->assertEquals('post', $request->getMethod());
        $this->assertTrue($request->isPost());

        $_SERVER['REQUEST_METHOD'] = 'PUT';
        $this->assertEquals('put', $request->getMethod());
        $this->assertTrue($request->isPut());

        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $this->assertEquals('delete', $request->getMethod());
        $this->assertTrue($request->isDelete());
    }

    function testInputData() {

        $app = $this->startApp();
        $request = $app->createRequest('/');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET['foo'] = 'bar';
        $this->assertEquals(array('foo' => 'bar'), $request->getInputData());

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['foo2'] = 'bar2';
        $this->assertEquals(array('foo2' => 'bar2'), $request->getInputData());

        $_SERVER['REQUEST_METHOD'] = 'PUT';
        $file = tempnam('/tmp/', 'phpunit_put');
        file_put_contents($file, 'foo3=bar3');
        $request = $app->createRequest('/', array('put_data_file' => $file));
        $this->assertEquals(array('foo3' => 'bar3'), $request->getInputData());
        unlink($file);

        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $file = tempnam('/tmp/', 'phpunit_delete');
        file_put_contents($file, 'foo4=bar4');
        $request = $app->createRequest('/', array('delete_data_file' => $file));
        $this->assertEquals(array('foo4' => 'bar4'), $request->getInputData());
        unlink($file);

    }

}
