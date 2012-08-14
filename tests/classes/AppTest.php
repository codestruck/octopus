<?php
/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class AppTests extends Octopus_App_TestCase {

    function testAutoAliasSlashToSysWelcome() {

        $app = $this->getApp();

        $r = $app->getRouter();
        $this->assertEquals('sys/welcome', $r->resolve('/'));

    }

    function testAlias() {

        $app = $this->getApp();
        $app->alias('/products/view/{$id}', '/products/{$id}', array('id' => '\d+'));

        $req = $app->createRequest('/products/90');
        $this->assertEquals('products/view/90', $req->getResolvedPath());

    }


	function testLoadRoutesFile() {

		file_put_contents(
			$this->getSiteDir() . 'routes.php',
			<<<END
<?php
\$NAV->alias('/routes-nav-alias', '/routes-long-nav-alias');
\$ROUTES->add('/routes-routes-alias', '/routes-long-routes-alias');
\$APP->alias('/routes-upper-app-alias', '/routes-aa-upper');
\$app->alias('/routes-lower-app-alias', '/routes-aa-lower')
?>
END
		);

		file_put_contents(
			$this->getSiteDir() . 'nav.php',
			<<<END
<?php
\$NAV->alias('/nav-nav-alias', '/nav-long-nav-alias');
\$ROUTES->add('/nav-routes-alias', '/nav-long-routes-alias');
\$APP->alias('/nav-upper-app-alias', '/nav-aa-upper');
\$app->alias('/nav-lower-app-alias', '/nav-aa-lower')
?>
END
		);

		$app = $this->startApp();
		$r = $app->getRouter();

		$this->assertEquals('/routes-long-nav-alias', $r->resolve('/routes-nav-alias'));
		$this->assertEquals('/routes-long-routes-alias', $r->resolve('/routes-routes-alias'));
		$this->assertEquals('/routes-upper-app-alias', $r->resolve('/routes-aa-upper'));
		$this->assertEquals('/routes-lower-app-alias', $r->resolve('/routes-aa-lower'));

		$this->assertEquals('/nav-long-nav-alias', $r->resolve('/nav-nav-alias'));
		$this->assertEquals('/nav-long-routes-alias', $r->resolve('/nav-routes-alias'));
		$this->assertEquals('/nav-upper-app-alias', $r->resolve('/nav-aa-upper'));
		$this->assertEquals('/nav-lower-app-alias', $r->resolve('/nav-aa-lower'));

	}

    function testRespectSmartyTemplateDirInAppOptions() {

        // SoleCMS passes the 'template_dir' option to Octopus_App's constructor
        // to make sure that templates in the site's templates/ dir are rendered

        $dir = sys_get_temp_dir();
        $app = $this->startApp(array('template_dir' => $dir));

        Octopus::loadExternal('smarty');
        $smarty = Octopus_Smarty::trusted();

        $this->assertEquals(array($dir), $smarty->smarty->template_dir);

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

        $foomtime = filemtime($fooThemeDir . 'test.js');

        $page = Octopus_Html_Page::singleton();
        $page->addJavascript('/test.js');

        $settings->set('site.theme', 'foo');

        $resp = $app->getResponse('/whatever/blah', true);
        $this->assertEquals('foo', $resp->theme, 'theme is correct on response');

        $this->assertHtmlEquals(
            <<<END
<head>
    <title></title>
    <meta http-equiv="Content-type" content="text/html; charset=UTF-8" />
    <script type="text/javascript" src="/site/themes/foo/test.js?$foomtime"></script>
</head>
END
            ,
            $resp->render(true)
        );

        $settings->set('site.theme', 'bar');
        Octopus_Smarty::singleton()->reset();

        $barmtime = filemtime($barThemeDir . 'test.js');

        $resp = $app->getResponse('/whatever/blah', true);
        $this->assertHtmlEquals(
            <<<END
<head>
    <title></title>
    <meta http-equiv="Content-type" content="text/html; charset=UTF-8" />
    <script type="text/javascript" src="/site/themes/bar/test.js?$barmtime"></script>
</head>
END
            ,
            $resp->render(true)
        );

    }

    function testFindCssInThemeDir() {

        $app = $this->startApp();
        $settings = $app->getSettings();

        $fooThemeDir = $this->getSiteDir() . 'themes/foo/';
        $barThemeDir = $this->getSiteDir() . 'themes/bar/';

        mkdir($fooThemeDir, 0777, true);
        mkdir($fooThemeDir . 'templates/html/', 0777, true);
        mkdir($barThemeDir, 0777, true);
        mkdir($barThemeDir . 'templates/html/', 0777, true);

        touch($fooThemeDir . 'test.css');
        touch($barThemeDir . 'test.css');
        $foomtime = filemtime($fooThemeDir . 'test.css');
        $barmtime = filemtime($barThemeDir . 'test.css');

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
    <link href="/site/themes/foo/test.css?$foomtime" rel="stylesheet" type="text/css" media="all" />
</head>
END
            ,
            $resp->render(true)
        );

        $settings->set('site.theme', 'bar');
        Octopus_Smarty::singleton()->reset();

        $resp = $app->getResponse('/whatever/blah', true);
        $this->assertHtmlEquals(
            <<<END
<head>
    <title></title>
    <meta http-equiv="Content-type" content="text/html; charset=UTF-8" />
    <link href="/site/themes/bar/test.css?$barmtime" rel="stylesheet" type="text/css" media="all" />
</head>
END
            ,
            $resp->render(true)
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

        $this->markTestSkipped('trigger_error is causing problems');

        $app = $this->startApp();

        uncancel_redirects();
        $this->assertTrue(should_redirect(), 'Should be able to redirect intially');

        $this->errorHandlerCalled = false;
        $this->prevErrorHandler = set_error_handler(array($this, 'errorHandler'));

        trigger_error("\n\n(You can ignore this. It is part of a test to make sure redirects are cancelled when errors happen.)\n\n", $level);
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

