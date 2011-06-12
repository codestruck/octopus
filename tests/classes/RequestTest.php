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

        $controllerFile = $app->getOption('SITE_DIR') . 'controllers/Simple.php';
        touch($controllerFile);

        $tests = array(
           '/simple' => array('file' => $controllerFile, 'potential_names' => array('SimpleController'), 'action' => 'index', 'original_action' => '', 'args' => array()),
           '/simple/index/foo/bar' => array('file' => $controllerFile, 'potential_names' => array('SimpleController'), 'action' => 'index', 'original_action' => 'index', 'args' => array('foo', 'bar')),
           '/simple/index' => array('file' => $controllerFile, 'potential_names' => array('SimpleController'), 'action' => 'index', 'original_action' => 'index', 'args' => array()),
           '/simple/view/57' => array('file' => $controllerFile, 'potential_names' => array('SimpleController'), 'action' => 'view', 'original_action' => 'view', 'args' => array(57)),
           '/simple/view/andedit/57' => array('file' => $controllerFile, 'potential_names' => array('SimpleController'), 'action' => 'view', 'original_action' => 'view', 'args' => array('andedit', 57)),
        );

        foreach($tests as $path => $expected) {

            $req = $app->createRequest($path);

            $this->assertEquals(
                $expected,
                $req->getControllerInfo(),
                "Failed on '$path'"
            );

        }

    }

    function testFindControllerInSubdir() {

        $app = $this->startApp();

        $controllerFile = $app->getOption('SITE_DIR') . 'controllers/api/1/Deep.php';
        mkdir(dirname($controllerFile), 0777, true);
        touch($controllerFile);

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

    function assertControllerInfoMatches($expected, $info, $path = null) {

        if ($info instanceof Octopus_Request) {
            $path = $info->getPath();
            $info = $info->getControllerInfo();
        }

        if ($expected === false) {
            $this->assertFalse($info, "Failed on '$path'");
            return;
        }

        $this->assertTrue(is_array($info), "\$info was not an array. Failed on '$path'");

        foreach($expected as $key => $value) {
            $this->assertEquals($value, $info[$key], "Failed on '$key' for path '$path'");
        }
    }

    function testFindDasherizedControllers() {

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

    function xtestInvalidActionBecomesArg() {

        $app = $this->startApp();

        $controllerFile = $this->createControllerFile('InvalidActionBecomesArg');

        $tests = array(
           '/invalid-action-becomes-arg/57' => array('file' => $controllerFile, 'action' => 'index', 'args' => array(57)),
           '/invalid-action-becomes-arg/index/57' => array('file' => $controllerFile, 'action' => 'index', 'args' => array(57))
        );

        foreach($tests as $path => $expected) {
            $req = $app->createRequest($path);
            $this->assertControllerInfoMatches($expected, $req);
        }


    }

    function xtestFindApiControllers() {

        $app = $this->startApp();

        $controllerFile = $this->createControllerFile('api/1/FindApiTest');

        $tests = array(
           '/api/1/find-api-test/view/57' => array('file' => $controllerFile, 'action' => 'view', 'args' => array(57)),
           '/api/1/find-api-test/57' => array('file' => $controllerFile, 'action' => '', 'args' => array(57))
        );

        foreach($tests as $path => $expected) {
            $req = $app->createRequest($path);
            $this->assertControllerInfoMatches($expected, $req);
        }

    }



}

?>
