<?php

class ThemeTest extends Octopus_App_TestCase {

    function testGetThemeFile() {

    	$app = $this->getApp();
    	$settings = $app->getSettings();
    	$settings->set('site.theme', 'test');

        recursive_touch($this->getSiteDir() . 'themes/test/foo.bar');

        $file = get_theme_file('foo.bar');

        $this->assertEquals($this->getSiteDir() . 'themes/test/foo.bar', $file);
    }

    function testAddThemeJavascript() {

    	$this->startApp();

    	$dirs = array(
	    	$this->getOctopusDir() => '/octopus/',
	    	$this->getSiteDir() => '/site/'
	    );

    	foreach($dirs as $dir => $url) {

    		$page = Octopus_Html_Page::singleton();
			$page->reset();

    		recursive_touch($dir . 'themes/test/script/test.js');
    		add_theme_javascript('script/test.js');

    		$files = $page->getJavascriptFiles();
    		$this->assertEquals(1, count($files), 'only 1 javascript file added');
    		$this->assertEquals($url . 'themes/test/script/test.js', $files[0]['file'], 'URL for added javascript is correct');

    		$page->reset();
    		recursive_delete($dir . 'themes');
    	}

    }

    function testAddThemeCss() {

    	$this->startApp();

    	$dirs = array(
	    	$this->getOctopusDir() => '/octopus/',
	    	$this->getSiteDir() => '/site/'
	    );

    	foreach($dirs as $dir => $url) {

    		$page = Octopus_Html_Page::singleton();
			$page->reset();

    		recursive_touch($dir . 'themes/test/css/test.css');
    		add_theme_css('/css/test.css');

    		$files = $page->getCssFiles();
    		$this->assertEquals(1, count($files), 'only 1 css file added');
    		$this->assertEquals($url . 'themes/test/css/test.css', $files[0]['file'], 'URL for added css is correct');

    		$page->reset();
    		recursive_delete($dir . 'themes');
    	}

    }


}

?>
