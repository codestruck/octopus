<?php

/**
 * Tests of the execution/rendering pipeline.
 */
class PipelineTests extends Octopus_App_TestCase {

    function testDontAddSlashForDefaultControllerActionsWithoutController() {

        $siteDir = $this->getSiteDir();
        file_put_contents(
            "{$siteDir}/views/test.tpl",
            "Hi there"
        );

        $app = $this->getApp();

        $req = $app->createRequest('/test');
        $this->assertEquals('DefaultController', $req->getControllerClass());
        $this->assertEquals('test', $req->getAction(), 'Action is test');
        $this->assertEquals('test', $req->getRequestedAction(), 'Requested action is test');
        $this->assertEquals(array(), $req->getActionArgs(), 'Action gets no arguments');

        $resp = $app->getResponse('/test', true);

        $this->assertEquals(200, $resp->getStatus(), "Should not redirect to /test/");

    }

    function testDefaultActionReceivesActionAndArgs() {

        $siteDir = $this->getSiteDir();

        file_put_contents(
            "{$siteDir}/controllers/DefaultAction.php",
            <<<END
<?php

class DefaultActionController extends Octopus_Controller {

    function _default(\$action, \$args) {
        \$GLOBALS['action:' . \$action] = \$args;
    }

}

?>
END
        );


        $app = $this->startApp();

        $this->createViewFile('default_action/foo');

        unset($GLOBALS['action:foo']);
        $response = $app->getResponse('default-action/foo/arg1/arg2', true);

        $this->assertEquals(
            array('arg1', 'arg2'),
            $GLOBALS['action:foo']
        );

    }

    function testBeforeAndAfterActionsCalled() {

        $siteDir = $this->getSiteDir();

        file_put_contents(
            "{$siteDir}/controllers/BeforeAndAfter.php",
            <<<END
<?php

class BeforeAndAfterController extends Octopus_Controller {

    var \$i = 0;

    function _before(\$action, \$args) {
        \$GLOBALS[__METHOD__] = array(\$this->i++, \$action, \$args);
    }

    function _after(\$action, \$args, \$data) {
        \$GLOBALS[__METHOD__] = array(\$this->i++, \$action, \$args);
    }

    function _before_default(\$action, \$args) {
        \$GLOBALS[__METHOD__] = array(\$this->i++, \$args);
    }

    function _after_default(\$action, \$args, \$data) {
        \$GLOBALS[__METHOD__] = array(\$this->i++, \$args);
    }

    function _before_foo(\$args) {
        \$GLOBALS[__METHOD__] = array(\$this->i++, \$args);
    }

    function _after_foo(\$args, \$data) {
        \$GLOBALS[__METHOD__] = array(\$this->i++, \$args);
    }

    function foo(\$arg1, \$arg2) {
        \$GLOBALS[__METHOD__] = array(\$this->i++, \$arg1, \$arg2);
    }

    function _before_missing(\$args) {
        \$GLOBALS[__METHOD__] = array(\$this->i++, \$args);
    }

    function _after_missing(\$args, \$data) {
        \$GLOBALS[__METHOD__] = array(\$this->i++, \$args, \$data);
    }

    function _before_cancel(\$args) {
        \$GLOBALS[__METHOD__] = array(\$this->i++, \$args);
        return false;
    }

    function cancel(\$arg1, \$arg2) {
        \$GLOBALS[__METHOD__] = array(\$this->i++, \$arg1, \$arg2);
    }

    function _after_cancel(\$args, \$data) {
        \$GLOBALS[__METHOD__] = array(\$this->i++, \$args, \$data);
    }

    function emptyAction(\$arg1, \$arg2) {
    \$GLOBALS[__METHOD__] = array(\$this->i++, \$arg1, \$arg2);
    }

    function _before_emptyAction(\$args) {
        \$GLOBALS[__METHOD__] = array(\$this->i++, \$args);
    }

    function _after_emptyAction(\$args, \$data) {
        \$GLOBALS[__METHOD__] = array(\$this->i++, \$args, \$data);
    }



}

?>
END
        );

        $app = $this->startApp();

        $this->createViewFile('_before_and_after/foo');
        $this->createViewFile('_before_and_after/missing');
        $this->createViewFile('_before_and_after/cancel');
        $this->createViewFile('_before_and_after/empty');

        $resp = $app->getResponse('before-and-after/foo/arg1/arg2', true);

        $this->assertEquals(array(0, 'foo', array('arg1', 'arg2')), $GLOBALS['BeforeAndAfterController::_before'], '_before is wrong');
        $this->assertEquals(array(1, array('arg1', 'arg2')), $GLOBALS['BeforeAndAfterController::_before_foo'], '_before_foo is wrong');
        $this->assertEquals(array(2, 'arg1', 'arg2'), $GLOBALS['BeforeAndAfterController::foo'], 'foo is wrong');
        $this->assertEquals(array(3, array('arg1', 'arg2')), $GLOBALS['BeforeAndAfterController::_after_foo'], '_after_foo is wrong');
        $this->assertEquals(array(4, 'foo', array('arg1', 'arg2')), $GLOBALS['BeforeAndAfterController::_after'], '_after is wrong');


        unset($GLOBALS['BeforeAndAfterController::_before']);
        unset($GLOBALS['BeforeAndAfterController::_after']);

        $resp = $app->getResponse('before-and-after/missing/arg1/arg2', true);
        $this->assertEquals(array(0, 'missing', array('arg1', 'arg2')), $GLOBALS['BeforeAndAfterController::_before'], '_before is wrong');
        $this->assertEquals(array(1, array('arg1', 'arg2')), $GLOBALS['BeforeAndAfterController::_before_missing'], '_before_missing is wrong');
        $this->assertEquals(array(2, array('arg1', 'arg2')), $GLOBALS['BeforeAndAfterController::_before_default'], '_before_default is wrong');
        $this->assertEquals(array(3, array('arg1', 'arg2')), $GLOBALS['BeforeAndAfterController::_after_default'], '_after_default is wrong');
        $this->assertEquals(array(4, array('arg1', 'arg2'), null), $GLOBALS['BeforeAndAfterController::_after_missing'], '_after_missing is wrong');
        $this->assertEquals(array(5, 'missing', array('arg1', 'arg2')), $GLOBALS['BeforeAndAfterController::_after'], '_after is wrong');

        unset($GLOBALS['BeforeAndAfterController::_before']);
        unset($GLOBALS['BeforeAndAfterController::_after']);
        unset($GLOBALS['BeforeAndAfterController::cancel']);

        $resp = $app->getResponse('before-and-after/cancel/arg1/arg2', true);
        $this->assertEquals(array(0, 'cancel', array('arg1', 'arg2')), $GLOBALS['BeforeAndAfterController::_before'], '_before is wrong');
        $this->assertEquals(array(1, array('arg1', 'arg2')), $GLOBALS['BeforeAndAfterController::_before_cancel'], '_before_cancel is wrong');
        $this->assertFalse(isset($GLOBALS['BeforeAndAfterController::cancel']), 'cancel should not have been called');
        $this->assertFalse(isset($GLOBALS['BeforeAndAfterController::_after_cancel']), '_after_cancel should not have been called.');
        $this->assertFalse(isset($GLOBALS['BeforeAndAfterController::_after']), '_after should not have been called.');

        unset($GLOBALS['BeforeAndAfterController::_before']);
        unset($GLOBALS['BeforeAndAfterController::_after']);
        unset($GLOBALS['BeforeAndAfterController::_before_emptyAction']);
        unset($GLOBALS['BeforeAndAfterController::_after_emptyAction']);
        unset($GLOBALS['BeforeAndAfterController::emptyAction']);

        $resp = $app->getResponse('before-and-after/empty/arg1/arg2', true);
        $this->assertEquals(array(0, 'empty', array('arg1', 'arg2')), $GLOBALS['BeforeAndAfterController::_before'], '_before is wrong for *Action');
        $this->assertEquals(array(1, array('arg1', 'arg2')), $GLOBALS['BeforeAndAfterController::_before_emptyAction'], '_before_emptyAction is wrong for *Action');
        $this->assertEquals(array(2, 'arg1', 'arg2'), $GLOBALS['BeforeAndAfterController::emptyAction'], 'emptyAction is wrong');
        $this->assertEquals(array(3, array('arg1', 'arg2'), null), $GLOBALS['BeforeAndAfterController::_after_emptyAction'], '_after_emptyAction is wrong');
        $this->assertEquals(array(4, 'empty', array('arg1', 'arg2')), $GLOBALS['BeforeAndAfterController::_after'], '_after is wrong for *Action');

    }

    /**
     * @dataProvider privateAndProtectedPathProvider
     */
    public function testPrivateAndProtectedMethodsNotCallableAsActions($path) {

        $app = $this->startApp();

        global $_PP_TEST_CASE;
        $_PP_TEST_CASE = $this;

        $this->createControllerFile(
            'PrivateAndProtectedActions',
            <<<END
<?php

            class PrivateAndProtectedActionsController extends Octopus_Controller {

                private function privateAction() {
                    global \$_PP_TEST_CASE;
                    \$_PP_TEST_CASE->assertTrue(false, __METHOD__ . ' was called on the controller!');
                }

                private function privateFoo() {
                    global \$_PP_TEST_CASE;
                    \$_PP_TEST_CASE->assertTrue(false, __METHOD__ . ' was called on the controller!');
                }

                protected function protectedAction() {
                    global \$_PP_TEST_CASE;
                    \$_PP_TEST_CASE->assertTrue(false, __METHOD__ . ' was called on the controller!');
                }

                protected function protectedFoo() {
                    global \$_PP_TEST_CASE;
                    \$_PP_TEST_CASE->assertTrue(false, __METHOD__ . ' was called on the controller!');
                }

            }

            ?>
END
        );
        $this->createViewFile('private_and_protected_actions/private');
        $this->createViewFile('private_and_protected_actions/private_foo');
        $this->createViewFile('private_and_protected_actions/protected');
        $this->createViewFile('private_and_protected_actions/protected_foo');

        $app->getResponse($path, true);

    }

    public static function privateAndProtectedPathProvider() {

        return array(
            array('/private-and-protected-actions/private'),
            array('/private-and-protected-actions/private-foo'),
            array('/private-and-protected-actions/protected'),
            array('/private-and-protected-actions/protected-foo')
        );
    }

    function testBeforeAndAfterNotCallableAsActions() {

        $app = $this->startApp();

        $this->createControllerFile(
            'BeforeAndAfterNotActions',
            <<<END
<?php
            class BeforeAndAfterNotActionsController extends Octopus_Controller {

                var \$i = 0;

                public function _before(\$action, \$args) {
                    \$GLOBALS[__METHOD__] = array(\$this->i++, \$action, \$args);
                }

                public function _after(\$action, \$args, \$data) {
                    \$GLOBALS[__METHOD__] = array(\$this->i++, \$action, \$args);
                    return \$data;
                }


                public function _before_default(\$action, \$args) {
                    \$GLOBALS[__METHOD__] = array(\$this->i++, \$action, \$args);
                }

                public function _after_default(\$action, \$args, \$data) {
                    \$GLOBALS[__METHOD__] = array(\$this->i++, \$action, \$args);
                    return \$data;
                }


                public function _before_foo(\$args) {
                    \$GLOBALS[__METHOD__] = array(\$this->i++, \$args);
                }

                public function foo(\$arg) {
                    \$GLOBALS[__METHOD__] = array(\$this->i++, \$arg);
                }

                public function _after_foo(\$args, \$data) {
                    \$GLOBALS[__METHOD__] = array(\$this->i++, \$args, \$data);
                    return \$data;
                }


            }
            ?>
END
        );

        $this->createViewFile('before-and-after-not-actions/_before_foo');
        $this->createViewFile('before-and-after-not-actions/_after_foo');
        $this->createViewFile('before-and-after-not-actions/_before');
        $this->createViewFile('before-and-after-not-actions/_after');
        $this->createViewFile('before-and-after-not-actions/_before_default');
        $this->createViewFile('before-and-after-not-actions/_after_default');


        $resp = $app->getResponse('before-and-after-not-actions/_before_foo', true);

        foreach(array('_before_foo', '_after_foo') as $m) {
            $this->assertFalse(isset($GLOBALS['BeforeAndAfterNotActionsController::' . $m]), "$m is set");
        }
    }

    function testRedirectToAddSlashOnIndex() {

        $app = $this->startApp();

        $this->createControllerFile('AddSlashTest');
        $this->createViewFile('add_slash_test/index');

        $resp = $app->getResponse('/add-slash-test', true);

        $this->assertEquals(
            <<<END
HTTP/1.1 302 Found
Location: /add-slash-test/
END
            ,
            trim($resp)
        );

        $resp = $app->getResponse('/add-slash-test/', true);
        $this->assertEquals(200, $resp->getStatus());

    }

    function testKeepQuerystringWhenAddingSlash() {

        $app = $this->startApp();
        $this->createControllerFile('KeepQsWhenAddingSlash');


        $tests = array(
            "/keep-qs-when-adding-slash",
        );

        $_GET['foo'] = 'bar';

        foreach($tests as $path) {
            $resp = $app->getResponse($path, true);
            $this->assertEquals(
                <<<END
HTTP/1.1 302 Found
Location: /keep-qs-when-adding-slash/?foo=bar
END
                ,
                trim($resp)
            );
        }


    }

    function testDontPassQueryStringAsActionToBeforeAndAfter() {

        $GLOBALS['OCTOPUS_TEST_CASE'] = $this;
        $GLOBALS['_BEFORE_CALLED'] = false;
        $GLOBALS['_AFTER_CALLED'] = false;

        $app = $this->getApp();
        $this->createControllerFile(
            'DontPassQsInAction',
            <<<END
<?php

            class DontPassQsInActionController extends Octopus_Controller {

                public function _before(\$action, \$args) {
                    global \$OCTOPUS_TEST_CASE;
                    global \$_BEFORE_CALLED;

                    \$OCTOPUS_TEST_CASE->assertEquals('test', \$action);
                    \$_BEFORE_CALLED = true;
                }

                public function testAction() {

                }

                public function _after(\$action, \$args, \$result) {

                    global \$OCTOPUS_TEST_CASE;
                    global \$_AFTER_CALLED;

                    \$OCTOPUS_TEST_CASE->assertEquals('test', \$action);
                    \$_AFTER_CALLED = true;

                }

            }


            ?>
END
           );

           $resp = $app->getResponse('/dont-pass-qs-in-action/test?foo=bar', true);
           $this->assertTrue($GLOBALS['_BEFORE_CALLED'], '_before not called');
           $this->assertTrue($GLOBALS['_AFTER_CALLED'], '_after not called');



    }

    function testViewNotFoundReturns404() {

        $app = $this->startApp();
        $this->createControllerFile('ViewNotFound404');

        $resp = $app->getResponse('/view-not-found-404/', true);

        // Need to render $resp so it can realize the view doesn't exists
        $resp->render(true);

        $this->assertEquals(404, $resp->getStatus());


    }

    function testFullCacheDisabledByDefault() {

    	return $this->markTestSkipped('Disabling full cache temporarily');

    	$this->createFullCacheTestFiles('DisabledByDefault');

    	$app = $this->startApp(array('full_cache' => true));
    	$app->clearFullCache();

    	$resp = $app->getResponse('test-full-cache-disabled-by-default/default-cache');

    	$this->assertCacheFilesExist(false, 'test-full-cache-disabled-by-default/default-cache');

    }

    function testFullCacheOnlyGetRequestsCached() {

    	return $this->markTestSkipped('Disabling full cache temporarily');

    	$this->createFullCacheTestFiles('OnlyGet');

    	$app = $this->startApp(array('full_cache' => true));

    	foreach(array('POST', 'PUT', 'DELETE', 'GET') as $method) {

    		$appMethod = 'get' . camel_case($method, true) . 'Response';
    		$resp = $app->$appMethod('/test-full-cache-only-get/cache');

    		$this->assertCacheFilesExist($method == 'GET', '/test-full-cache-only-get/cache', $method == 'GET' ? 'Cache file exists for GET' : "Cache file does not exist for $method");

    	}

    }

    function testFullCacheOnlyCachedWhenQuerystringEmpty() {

		return $this->markTestSkipped('Disabling full cache temporarily');

    	$this->createFullCacheTestFiles('EmptyQuerystring');

    	$app = $this->startApp(array('full_cache' => true));

    	foreach(array('GET', 'POST', 'PUT', 'DELETE') as $method) {

    		$appMethod = 'get' . camel_case($method, true) . 'Response';
    		$resp = $app->$appMethod('/test-full-cache-empty-querystring/cache?foo=bar');

    		$this->assertCacheFilesExist(false, '/test-full-cache-empty-querystring/cache', "No cache file created for $method with querystring");

    	}

    }

    function testFullCacheClearCacheDir() {

    	return $this->markTestSkipped('Disabling full cache temporarily');

    	$this->createFullCacheTestFiles('ClearCacheDir');

    	$app = $this->startApp(array('full_cache' => true));

		$resp = $app->getGetResponse('/test-full-cache-clear-cache-dir/cache');

		$this->assertCacheFilesExist(true, '/test-full-cache-clear-cache-dir/cache');
    	$app->clearFullCache();
    	$this->assertCacheFilesExist(false, '/test-full-cache-clear-cache-dir/cache', 'no cache files after clearFullCache');

    }

    function testFullCacheAppendsTimestampForHtml() {

    	return $this->markTestSkipped('Disabling full cache temporarily');

    	$this->createFullCacheTestFiles('AppendTimestamp');

		$app = $this->startApp(array('full_cache' => true));

    	$resp = $app->getGetResponse('/test-full-cache-append-timestamp/cache');

    	$cacheContents = file_get_contents($app->OCTOPUS_CACHE_DIR . 'full/test-full-cache-append-timestamp/cache/index.html');
    	$this->assertTrue(!!preg_match('/<!-- ots(\d+) -->/', $cacheContents, $m), 'timestamp comment found in html');
    	$this->assertTrue(time() - $m[1] < 3, 'Timestamp is recent');

    }

    function testNoFullCacheForNotHtml() {

    	return $this->markTestSkipped('Disabling full cache temporarily');

    	$this->createFullCacheTestFiles('NonHtml');

		$app = $this->startApp(array('full_cache' => true));

    	$resp = $app->getGetResponse('/test-full-cache-non-html/cache-plain-text');

    	$this->assertCacheFilesExist(false, '/test-full-cache-non-html/cache-plain-text');

    }

   	function assertCacheFilesExist($exists, $path, $message = null) {

   		$app = $this->getApp();

		$cacheFile = $app->OCTOPUS_CACHE_DIR . 'full/' . trim($path, '/') . '/index.html';
		$gzCacheFile = $cacheFile . '.gz';

		if (!$message) {
			$message = $exists ? 'Cache file exists' : 'Cache file does not exist';
		}

		$this->assertEquals(!!$exists, is_file($cacheFile), $message . ' (' . $cacheFile . ')');
		$this->assertEquals(!!$exists, is_file($gzCacheFile), $message . ' (' . $gzCacheFile . ')');

    }

    function createFullCacheTestFiles($discriminator) {

    	$pageTpl = $this->getSiteDir() . 'themes/default/templates/html/page.php';
    	file_put_contents($pageTpl, '<div class="page-tpl"> <?php echo $view_content ?> </div>');


    	$this->createControllerFile("TestFullCache{$discriminator}", <<<END
<?php

class TestFullCache{$discriminator}Controller extends Octopus_Controller {

	public function defaultCacheAction() {

	}

	public function cacheAction() {
		\$this->cache = true;
	}

	public function cachePlainTextAction() {
		\$this->cache = true;
		\$this->response->setContentType('text/plain');
	}

}

END
  		);

    	$discriminator = underscore($discriminator);

    	$this->createViewFile("test_full_cache_{$discriminator}/default_cache.tpl", 'FOO');
    	$this->createViewFile("test_full_cache_{$discriminator}/cache.tpl", 'FOO');
    	$this->createViewFile("test_full_cache_{$discriminator}/no_cache.tpl", 'FOO');


    }

}
