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

        $app = $this->getApp();
        $renderer = $app->getRenderer();

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
            $app = $this->getApp();
            $renderer = $app->getRenderer();
            $actual = $renderer->findView($actual, null);
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

    function testFullCache() {

    	$pageTpl = $this->getSiteDir() . 'themes/default/templates/html/page.php';
    	file_put_contents($pageTpl, '<?php echo $view_content ?>');

    	$controllerFile = $this->createControllerFile('TestFullCache');
    	$this->createViewFile(array(
    		'test_full_cache/cached.tpl',
    		'test_full_cache/uncached.tpl',
    	), __METHOD__);

    	$app = $this->getApp();
    	$renderer = $app->getRenderer();
    	$renderer->enableFullCache();

    	$app->getGetResponse('/test-full-cache/cached');
    	$this->assertTrue(
    		is_file($app->OCTOPUS_CACHE_DIR . 'full/test-full-cache/cached/index.html'),
    		'cache file found'
    	);

    	$cacheContents = file_get_contents($app->OCTOPUS_CACHE_DIR . 'full/test-full-cache/cached/index.html');

    	$this->assertTrue(!!preg_match('#<!-- \d+ -->#', $cacheContents), 'timestamp appended to cache contents');
    	$cacheContents = preg_replace('#<!-- \d+ -->#', '', $cacheContents);

    	$this->assertHtmlEquals(
    		<<<END
RendererTest::testFullCache
END
			,
			$cacheContents
    	);

    	$renderer->disableFullCache();
    	$app->getGetResponse('/test-full-cache/uncached');
    	$this->assertFalse(
    		is_file($app->OCTOPUS_CACHE_DIR . 'full/test-full-cache/uncached/index.html'),
    		'uncached file not found'
    	);


    }

    function testFullCacheDisabledByDefault() {

    	$app = $this->getApp();
    	$renderer = $app->getRenderer();
    	$this->assertFalse($renderer->isFullCacheEnabled(), 'full caching disabled by default');

    }

    function testFullCacheOnlyGetRequestsCached() {

    	$pageTpl = $this->getSiteDir() . 'themes/default/templates/html/page.php';
    	file_put_contents($pageTpl, '<div class="page-tpl"> <?php echo $view_content ?> </div>');

    	$controllerFile = $this->createControllerFile('TestFullCache');
    	$this->createViewFile(array(
    		'test_full_cache/cached.tpl',
    	), __METHOD__);

    	$app = $this->getApp();
    	$renderer = $app->getRenderer();
    	$renderer->enableFullCache();

    	foreach(array('post', 'put', 'delete') as $method) {

    		$appMethod = 'get' . ucwords($method) . 'Response';
    		$resp = $app->$appMethod('/test-full-cache/cached');

    		$this->assertFalse(is_file($this->getCacheDir() . 'full/test-full-cache/cached/index.html'), 'cache file not written for ' . $method);

    	}

    }

    function testFullCacheOnlyCachedWhenQuerystringEmpty() {

    	$pageTpl = $this->getSiteDir() . 'themes/default/templates/html/page.php';
    	file_put_contents($pageTpl, '<div class="page-tpl"> <?php echo $view_content ?> </div>');

    	$controllerFile = $this->createControllerFile('TestFullCache');
    	$this->createViewFile(array(
    		'test_full_cache/cached.tpl',
    	), __METHOD__);

    	$app = $this->getApp();
    	$renderer = $app->getRenderer();
    	$renderer->enableFullCache();

    	foreach(array('get', 'post', 'put', 'delete') as $method) {

    		$appMethod = 'get' . ucwords($method) . 'Response';
    		$resp = $app->$appMethod('/test-full-cache/cached?foo=bar');

    		$this->assertFalse(is_file($this->getCacheDir() . 'full/test-full-cache/cached/index.html'), 'cache file not written for ' . $method);

    	}

    }
}
