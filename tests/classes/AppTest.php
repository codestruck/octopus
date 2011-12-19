<?php

class AppTests extends Octopus_App_TestCase {

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

    function testRespectSmartyTemplateDirInAppOptions() {

    	// SoleCMS passes the 'template_dir' option to Octopus_App's constructor
    	// to make sure that templates in the site's templates/ dir are rendered

    	$dir = sys_get_temp_dir();
    	$app = $this->startApp(array('template_dir' => $dir));

    	Octopus::loadExternal('smarty');
    	$smarty = Octopus_Smarty::trusted();

    	$this->assertEquals(array($dir), $smarty->getTemplateDir());

    }

    function testFindJavascriptInThemeDir() {

    	$app = $this->getApp();
    	$settings = $app->getSettings();

    	$fooThemeDir = $this->getSiteDir() . 'themes/foo/';
    	$barThemeDir = $this->getSiteDir() . 'themes/bar/';

    	mkdir($fooThemeDir, 0777, true);
    	mkdir($fooThemeDir . 'templates/html/', 0777, true);
    	mkdir($barThemeDir, 0777, true);
    	mkdir($barThemeDir . 'templates/html/', 0777, true);

    	touch($fooThemeDir . 'test.js');
    	touch($barThemeDir . 'test.js');

    	file_put_contents($fooThemeDir . 'templates/html/page.tpl', '{$HEAD}');
    	file_put_contents($barThemeDir . 'templates/html/page.tpl', '{$HEAD}');

    	$page = Octopus_Html_Page::singleton();
    	$page->addJavascript('/test.js');

    	$settings->set('site.theme', 'foo');

    	$resp = $app->getResponse('/whatever/blah', true);
    	$this->assertHtmlEquals(
	    	<<<END
<head>
	<title></title>
	<meta http-equiv="Content-type" content="text/html; charset=UTF-8" />
	<script type="text/javascript" src="/site/themes/foo/test.js"></script>
</head>
END
			,
			$resp->getContent()
		);

		$settings->set('site.theme', 'bar');
		Octopus_Smarty::singleton()->reset();

    	$resp = $app->getResponse('/whatever/blah', true);
    	$this->assertHtmlEquals(
	    	<<<END
<head>
	<title></title>
	<meta http-equiv="Content-type" content="text/html; charset=UTF-8" />
	<script type="text/javascript" src="/site/themes/bar/test.js"></script>
</head>
END
			,
			$resp->getContent()
		);

    }

    function testFindCssInThemeDir() {

    	$app = $this->getApp();
    	$settings = $app->getSettings();

    	$fooThemeDir = $this->getSiteDir() . 'themes/foo/';
    	$barThemeDir = $this->getSiteDir() . 'themes/bar/';

    	mkdir($fooThemeDir, 0777, true);
    	mkdir($fooThemeDir . 'templates/html/', 0777, true);
    	mkdir($barThemeDir, 0777, true);
    	mkdir($barThemeDir . 'templates/html/', 0777, true);

    	touch($fooThemeDir . 'test.css');
    	touch($barThemeDir . 'test.css');

    	file_put_contents($fooThemeDir . 'templates/html/page.tpl', '{$HEAD}');
    	file_put_contents($barThemeDir . 'templates/html/page.tpl', '{$HEAD}');

    	$page = Octopus_Html_Page::singleton();
    	$page->reset();
    	$page->addCss('/test.css');
    	$settings->set('site.theme', 'foo');

    	$resp = $app->getResponse('/whatever/blah', true);
    	$this->assertHtmlEquals(
	    	<<<END
<head>
	<title></title>
	<meta http-equiv="Content-type" content="text/html; charset=UTF-8" />
	<link href="/site/themes/foo/test.css" rel="stylesheet" type="text/css" media="all" />
</head>
END
			,
			$resp->getContent()
		);

		$settings->set('site.theme', 'bar');
		Octopus_Smarty::singleton()->reset();

    	$resp = $app->getResponse('/whatever/blah', true);
    	$this->assertHtmlEquals(
	    	<<<END
<head>
	<title></title>
	<meta http-equiv="Content-type" content="text/html; charset=UTF-8" />
	<link href="/site/themes/bar/test.css" rel="stylesheet" type="text/css" media="all" />
</head>
END
			,
			$resp->getContent()
		);

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
