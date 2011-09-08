<?php

Octopus::loadClass('Octopus_View_Finder');

class ViewFinderTest extends Octopus_App_TestCase {

    function testFindViewsInSubdir() {

        $app = $this->startApp();

        $this->createControllerFile('test/ViewsInSubdir');
        $viewFile = $this->createViewFile('test/views_in_subdir/index.php');

        $tests = array(
            '/test/views-in-subdir',
            '/test/views-in-subdir/',
            '/test/views-in-subdir/index',
        );

        foreach($tests as $path) {

            $req = $app->createRequest($path);
            $this->assertViewInfoMatches($viewFile, $req);

        }

    }

    function testFindUnderscoreControllerViewsInSubdir() {

        $app = $this->startApp();

        $this->createControllerFile('Test_UnderscoreViewsInSubdir');
        $viewFile = $this->createViewFile('test/underscore_views_in_subdir/index.php');

        $tests = array(
            '/test/underscore-views-in-subdir',
            '/test/underscore-views-in-subdir/',
            '/test/underscore-views-in-subdir/index',
        );

        foreach($tests as $path) {

            $req = $app->createRequest($path);
            $this->assertViewInfoMatches($viewFile, $req);

        }

    }

    function testFindViewsForDashedNames() {

        $app = $this->startApp();

        $this->createControllerFile('FindDashView');
        $this->createControllerFile('subdir/FindSubdirDashView');

        $viewFile = $this->createViewFile('find-dash-view/action');
        $subdirViewFile = $this->createViewFile('subdir/find-subdir-dash-view/action');

        $tests = array(
            '/find-dash-view/action' => $viewFile,
            '/subdir/find-subdir-dash-view/action' => $subdirViewFile
        );

        foreach($tests as $path => $expected) {

            $req = $app->createRequest($path);

            $this->assertViewInfoMatches($expected, $req);

        }
    }

    function testFindUnderscoreViews() {

        $app = $this->startApp();

        $this->createControllerFile('UnderscoreView');
        $this->createViewFile(array('underscore_view/my_action'));

        $tests = array(
            '/underscore-view/my-action' => 'underscore_view/my_action.php'
        );

        foreach($tests as $path => $expected) {

            $req = $app->createRequest($path);
            $this->assertViewInfoMatches($app->SITE_DIR . 'views/' . $expected, $req);

        }


    }

    function testSimpleViewDiscovery() {

        $app = $this->startApp();

        $this->createControllerFile('Simple');
        $this->createViewFile(array('simple/index', 'simple/view'));

        $tests = array(
           '/simple' => 'simple/index.php',
           '/simple/index' => 'simple/index.php',
           '/simple/view/57' => 'simple/view.php',
           '/simple/view/andedit/57' => 'simple/view.php'
        );

        foreach($tests as $path => $expected) {

            $req = $app->createRequest($path);

            $this->assertViewInfoMatches($app->SITE_DIR . 'views/' . $expected, $req);

        }

    }

    function testFallbackViewDiscovery() {

        $app = $this->startApp();
        $finder = new Octopus_View_Finder();

        $controllerFile = $this->createControllerFile('admin/FallbackViewTest');
        $viewFile = $this->createViewFile('add');

        $tests = array(
           '/admin/fallback-view-test/add' => 'add',
        );

        foreach($tests as $path => $expected) {
            $req = $app->createRequest($path);
            $this->assertViewInfoMatches(
                $app->SITE_DIR . 'views/' . $expected . '.php',
                $req
            );
        }

        $controllerFile = $this->createControllerFile('Admin_UnderscoreFallbackViewTest');
        $viewFile = $this->createViewFile('admin/add');


        $tests = array(
           '/admin/fallback-view-test/add' => 'admin/add',
        );

        foreach($tests as $path => $expected) {
            $req = $app->createRequest($path);
            $this->assertViewInfoMatches(
                $app->SITE_DIR . 'views/' . $expected . '.php',
                $req
            );
        }


    }

    function assertViewInfoMatches($expected, $actual, $path = null) {

        if ($actual instanceof Octopus_Request) {
            $path = $actual->getPath();
            $finder = new Octopus_View_Finder();
            $actual = $finder->findView($actual, null);
        }

        $this->assertTrue(is_array($actual), "\$actual was not an array. Failed on '$path'.");

        if ($expected == false) {
            $this->assertFalse($actual['found'], "View was found when it shouldn't have been. Failed on '$path'");
            return;
        }

        if (is_string($expected)) {
            $expected = array('file' => $expected, 'found' => true);
        }

        foreach($expected as $key => $value) {
            $this->assertEquals($value, $actual[$key], "Failed on $path");
        }

    }



}

?>
