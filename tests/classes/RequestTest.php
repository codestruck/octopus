<?php

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
           '/find-controller/view//57/andedit' => array('file' => $controllerFile, 'potential_names' => array('Find_ControllerController', 'FindControllerController'), 'action' => 'view', 'original_action' => 'view', 'args' => array(57, 'andedit')),
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
           '/api/1/deep' => array('file' => $controllerFile, 'potential_names' => array('Api_1_DeepController', 'Api1DeepController', 'DeepController'), 'action' => 'index', 'original_action' => '', 'args' => array()),
           '/api/1/deep/index' => array('file' => $controllerFile, 'potential_names' => array('Api_1_DeepController', 'Api1DeepController', 'DeepController'), 'action' => 'index', 'original_action' => 'index', 'args' => array()),
           '/api/1/deep/view/57' => array('file' => $controllerFile, 'potential_names' => array('Api_1_DeepController', 'Api1DeepController', 'DeepController'), 'action' => 'view', 'original_action' => 'view', 'args' => array(57)),
           '/api/1/deep/view/57/something' => array('file' => $controllerFile, 'potential_names' => array('Api_1_DeepController', 'Api1DeepController', 'DeepController'), 'action' => 'view', 'original_action' => 'view', 'args' => array(57, 'something')),
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
           '/dash-dash' => array('file' => $controllerFile, 'potential_names' => array('Dash_DashController', 'DashDashController'), 'action' => 'index', 'original_action' => '', 'args' => array()),
           '/dash-dash/index' => array('file' => $controllerFile, 'potential_names' => array('Dash_DashController', 'DashDashController'), 'action' => 'index', 'original_action' => 'index', 'args' => array()),
           '/dash-dash/view/57' => array('file' => $controllerFile, 'potential_names' => array('Dash_DashController', 'DashDashController'), 'action' => 'view', 'original_action' => 'view', 'args' => array(57)),
           '/dash-dash/view/andedit/57' => array('file' => $controllerFile, 'potential_names' => array('Dash_DashController', 'DashDashController'), 'action' => 'view', 'original_action' => 'view', 'args' => array('andedit', 57)),
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
        }

    }



}

?>
