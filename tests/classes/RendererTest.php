<?php

class RendererTest extends Octopus_App_TestCase {

    function testFindViewsInSubdir() {

        $app = $this->getApp();

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

        $app = $this->getApp();

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

        $app = $this->getApp();

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

        $app = $this->getApp();

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

        $app = $this->getApp();

        $this->createControllerFile('Simple', <<<END
<?
	class SimpleController extends Octopus_Controller {

		public function viewAction() {

		}

	}
?>
END
		);
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

        $app = $this->getApp();

        $controllerFile = $this->createControllerFile('admin/FallbackViewTest', <<<END
<?php

class FallbackViewTestController extends Octopus_Controller {

	public function addAction() {

	}

}

?>
END
		);
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

    function testFindDeepViewsNoController() {

    	$this->createViewFile('some/deep/view/file.tpl', 'hi there');
    	$app = $this->getApp();

    	$resp = $app->getResponse('/some/deep/view/file');
    	$this->assertEquals(200, $resp->getStatus(), '200 status for view that exists');

    	$resp = $app->getResponse('/some/deep/view/that_does_not_exist');
    	$this->assertEquals(404, $resp->getStatus(), '404 for non-existent view');

    }

    function testViewFallbackFailsWhenActionNotDefined() {

    	$this->createControllerFile('StaticFallbackFailure');
    	$this->createViewFile('static_fallback_failure/foo.tpl');

    	$app = $this->getApp();

    	$resp = $app->getResponse('/static_fallback_failure/foo');
    	$this->assertEquals(200, $resp->getStatus());

    	$resp = $app->getResponse('/static_fallback_failure/foo/bar');
    	$this->assertEquals(404, $resp->getStatus(), 'fallback fails when action not defined');


    }

    function testFindPWAdminView() {

    	$this->createControllerFile('Admin_Products', <<<END
<?php

class AdminProductsController extends Octopus_Controller {

	public function requestsAction() {

	}

}

END
    	);

    	$this->createViewFile('admin/products/requests.tpl');

    	$app = $this->getApp();
    	$resp = $app->getResponse('/admin/products/requests');

    	$this->assertEquals(200, $resp->getStatus());

    }

    function assertViewInfoMatches($expected, Octopus_Request $request) {

        $app = $this->getApp();
        $renderer = new Octopus_Renderer_Template();
        $path = $request->getPath();
        $actual = $renderer->getViewFileForRequest($request);

        if ($expected == false) {
            $this->assertFalse($actual, "View was found when it shouldn't have been. Failed on '$path'");
            return;
        }


    	if ($expected != $actual) {
    		dump_r($renderer->getViewPaths($req, $req->getController()));
    	}

        $this->assertEquals($expected, $actual, "Failed on $path");

    }

}
