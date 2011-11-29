<?php

class AppTests extends Octopus_App_TestCase {

	function testAutoAliasSlashToHome() {

		$app = $this->getApp();

		$r = $app->getRouter();
		$this->assertEquals('home', $r->resolve('/'));

	}

	function testAlias() {

		$app = $this->getApp();
		$app->alias('/products/view/{$id}', '/products/{$id}', array('id' => '\d+'));

		$req = $app->createRequest('/products/90');
		$this->assertEquals('products/view/90', $req->getResolvedPath());

	}

    function testGetTheme() {

        $app = $this->startApp();
        $this->assertTrue(!!$app, 'app should be something');
        $settings = $app->getSettings();

        $settings->reset('site.theme');

        $this->assertEquals('default', $app->getTheme());

        $settings->set('site.theme', 'foo');
        $this->assertEquals('foo', $app->getTheme());

        $this->assertEquals('foo', $app->getTheme('/admin'));

        $settings->set('site.theme.admin', 'bar');
        $this->assertEquals('foo', $app->getTheme('/'));

        $this->assertEquals('bar', $app->getTheme('/admin'));
        $this->assertEquals('bar', $app->getTheme('/admin/'));
        $this->assertEquals('bar', $app->getTheme('/admin/whatever'));

    }

    function errorHandler($num, $str) {
        $this->errorHandlerCalled = $num;
        if ($this->prevErrorHandler) {
            $args = func_get_args();
            call_user_func_array($this->prevErrorHandler, $args);
        }
        return false;
    }

    /**
     * @dataProvider provideErrorLevels
     */
    function testCancelRedirectsOnError($level) {

        $app = $this->startApp();

        uncancel_redirects();
        $this->assertTrue(should_redirect(), 'Should be able to redirect intially');

        $this->errorHandlerCalled = false;
        $this->prevErrorHandler = set_error_handler(array($this, 'errorHandler'));

        @trigger_error('This error should cancel redirects', $level);
        $this->assertEquals($level, $this->errorHandlerCalled, 'error handler was not called: ' . implode(' | ', Octopus_Debug::getErrorReportingFlags($level)));

        if ($this->prevErrorHandler) set_error_handler($this->prevErrorHandler);

        $this->assertFalse(should_redirect(), 'redirect should have been squashed');

    }

    public static function provideErrorLevels() {

        return array(
            // TODO: Should we cancel for notices?
            //array(E_USER_NOTICE),
            array(E_USER_WARNING),
            // Even with @, USER_ERROR kills phpunit
            // array(E_USER_ERROR)

        );

    }

}


?>
