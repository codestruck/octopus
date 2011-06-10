<?php

class ControllerDiscoveryTest extends Octopus_App_TestCase {

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

            $found = Octopus_Dispatcher::findController($path, $app);

            $this->assertEquals(
                $expected,
                Octopus_Dispatcher::findController($path, $app),
                "Failed on '$path'"
            );

        }

    }

    function testSimpleFind() {

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

            $found = Octopus_Dispatcher::findController($path, $app);

            $this->assertEquals(
                $expected,
                Octopus_Dispatcher::findController($path, $app),
                "Failed on '$path'"
            );

        }

    }

    function testFindInSubdir() {

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

            $this->assertEquals(
                $expected,
                Octopus_Dispatcher::findController($path, $app)
            );

        }

    }

    function testDontAllowPathMonkeyBusiness() {

        $app = $this->startApp();

        $file1 = $app->getOption('SITE_DIR') . 'controllers/Foo.php';
        $file2 = $app->getOption('SITE_DIR') . 'controllers/subdir/Bar.php';

        mkdir(dirname($file2), 0777, true);
        touch($file1);
        touch($file2);

        $tests = array(
           '/foo' => array('file' => $file1),
           '/subdir/bar' => array('file' => $file2),
           '/subdir/../foo' => array('file' => $file1, 'potential_names' => array('FooController'))
        );

        foreach($tests as $path => $expected) {

            $info = Octopus_Dispatcher::findController($path, $app);

            if ($expected === false) {
                $this->assertFalse($info, "Failed on $path");
            } else {

                $this->assertTrue(is_array($info), "findController did not return an array for $path");

                foreach($expected as $key => $value) {
                    $this->assertEquals($value, $info[$key], "$key was wrong for '$path'");
                }
            }

        }


    }

}

?>
