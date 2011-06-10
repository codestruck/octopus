<?php

class DispatcherTest extends Octopus_App_TestCase {

    function testSimpleViewDiscovery() {

        $app = $this->startApp();

        $controllerFile = $app->getOption('SITE_DIR') . 'Controllers/Simple.php';
        touch($controllerFile);

        mkdir($app->getOption('SITE_DIR') . 'views/simple');
        touch($app->getOption('SITE_DIR') . 'views/simple/index.php');
        touch($app->getOption('SITE_DIR') . 'views/simple/view.php');

        $tests = array(
           '/simple' => 'index.php',
           '/simple/index' => 'index.php',
           '/simple/view/57' => 'view.php',
           '/simple/view/andedit/57' => 'view.php'
        );

        foreach($tests as $path => $expected) {

            $c = Octopus_Dispatcher::findController($path, $app);

            $this->assertEquals(
                $app->getOption('SITE_DIR') . 'views/simple/' . $expected,
                Octopus_Dispatcher::findView($c['file'], null, $c['action'], $app),
                "Failed on '$path'"
            );

        }

    }

    function testFallbackViewDiscovery() {

        $app = $this->startApp();

        $s = $app->getOption('SITE_DIR');
        mkdir("$s/controllers/admin");

        $controllerFile = $app->getOption('SITE_DIR') . 'controllers/admin/Test.php';
        touch($controllerFile);

        $file = $app->getOption('SITE_DIR') . 'views/add.php';
        touch($file);

        $tests = array(
           '/admin/test/add',
        );

        foreach($tests as $path) {

            $c = Octopus_Dispatcher::findController($path, $app);

            $this->assertEquals(
                $file,
                Octopus_Dispatcher::findView($c['file'], null, $c['action'], $app),
                "Failed on '$path'"
            );

        }

    }

}

?>
