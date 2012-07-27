<?php

class ThemeTest extends Octopus_App_TestCase {

	function testThemeDotPHPIncluded() {

		$app = $this->startApp();

		$settings = $app->getSettings();
		$settings->set('site.theme', 'test');

		$themeDir = $app->SITE_DIR . 'themes/test/';
		mkdir($themeDir, 0777, true);

		file_put_contents(
			$themeDir . 'theme.php',
			<<<END
<?php

	\$GLOBALS['testThemeDotPHPIncluded'] = 'included';

END
		);

		unset($GLOBALS['testThemeDotPHPIncluded']);

		$resp = $app->getResponse('/foo');
		$this->assertEquals('included', $GLOBALS['testThemeDotPHPIncluded']);

		recursive_delete($themeDir);

	}

    function testGetThemeFile() {

        $app = $this->getApp();
        $settings = $app->getSettings();
        $settings->set('site.theme', 'test');

        recursive_touch($app->SITE_DIR . 'themes/test/foo.bar');

        $resp = $app->getResponse('/foo'); // need current response for get_theme_file to work
        $file = get_theme_file('foo.bar');

        $this->assertEquals($this->getSiteDir() . 'themes/test/foo.bar', $file);
    }

    function testAddThemeJavascript() {

        $app  = $this->startApp();
        $settings = $app->getSettings();
        $settings->set('site.theme', 'test');

    	$resp = $app->getResponse('/foo'); // need current response

        $dirs = array(
            $this->getOctopusDir() => '/octopus/',
            $this->getSiteDir() => '/site/'
        );

        foreach($dirs as $dir => $url) {

            $page = Octopus_Html_Page::singleton();
            $page->reset();

            $file = $dir . 'themes/test/script/test.js';
            recursive_touch($file);
            $mtime = filemtime($file);
            add_theme_javascript('script/test.js');

            $files = $page->getJavascriptFiles();
            $this->assertEquals(1, count($files), 'only 1 javascript file added');
            $this->assertEquals($url . "themes/test/script/test.js?$mtime", $files[0]['file'], 'URL for added javascript is correct');

            $page->reset();
            recursive_delete($dir . 'themes/test');
        }

    }

    function testAddThemeCss() {

    	$app = $this->getApp();
    	$settings = $app->getSettings();
    	$settings->set('site.theme', 'test');

    	$resp = $app->getResponse('/foo'); // need current response

        $dirs = array(
            $this->getOctopusDir() => '/octopus/',
            $this->getSiteDir() => '/site/'
        );

        foreach($dirs as $dir => $url) {

            $page = Octopus_Html_Page::singleton();
            $page->reset();

            $file = $dir . 'themes/test/css/test.css';
            recursive_touch($file);
            $mtime = filemtime($file);
            add_theme_css('/css/test.css');

            $files = $page->getCssFiles();
            $this->assertEquals(1, count($files), 'only 1 css file added');
            $this->assertEquals($url . "themes/test/css/test.css?$mtime", $files[0]['file'], 'URL for added css is correct');

            $page->reset();
            recursive_delete($dir . 'themes/test');
        }

    }


}

?>
