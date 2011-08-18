<?php

class SmartyImageTest extends Octopus_App_TestCase {

	private $testImages;
	private $extensions = array('.jpg', '.png', '.gif');

	function setUp() {
		
		parent::setUp();

		@mkdir($this->getSiteDir() . 'images');

		// Put images in the sitedir
		foreach($this->extensions as $ext) {
			$file = dirname(__FILE__)  . '/octopus' . $ext;
			$testImages[] = $file;
			$this->assertTrue(copy($file, $this->getSiteDir() . 'images/octopus' . $ext), "Copy $file to sitedir");;
		}

		$this->startApp();

	}

	function assertSmartyEquals($expected, $value, $message = '') {
		
		$app = $this->startApp();
		Octopus::loadExternal('smarty');
		
		$s = Octopus_Smarty::singleton();

		$smartyDir = $this->getSiteDir() . 'smarty/';
		mkdir($smartyDir);
		
		$tplFile = $smartyDir . 'test.tpl';
		@unlink($tplFile);

		file_put_contents($tplFile, $value);

        $s->smarty->template_dir = array($smartyDir);
        $tpl = $s->smarty->createTemplate($smartyDir . 'test.tpl', array());
        
        $rendered = $tpl->fetch();
        $this->assertHtmlEquals($expected, $rendered, $message);
	}

	/**
	 * @dataProvider getSiteDirImages
	 */
	function testImage($file, $absoluteFile) {

		foreach(array('src', 'file') as $attr) {
			
			$test = <<<END
{image $attr="$file"}
END;
		
			$expected = <<<END
<img src="$absoluteFile" width="100" height="75" />
END;


			$this->assertSmartyEquals($expected, $test, "$file ($absoluteFile), $attr attribute");
		}


	}

	/**
	 * @dataProvider getSiteDirImages
	 */
	function dontTestImageInSiteDir($file, $absoluteFile) {

		$urlSiteDir = URL_BASE . 'site/';
		$name = basename($file);
		
		$test = <<<END
{img src="/images/$name"}
END;


		$expected = <<<END
<img src="{$urlSiteDir}images/$name" width="100" height="75" />
END;

		$this->assertSmartyEquals($expected, $test);
	}

	/**
	 * @dataProvider getTestImages
	 */
	function dontTestImageInOctopusDir($file) {

		$name = basename($file);
		$octopusFile = OCTOPUS_DIR . $name;

		$urlOctopusDir = URL_BASE . '/octopus/';

		$test = <<<END
{img src="/$name"}
END;
		$expected = <<<END
<img src="{$urlOctopusDir}$name" width="100" height="75" />
END;
		$this->assertSmartyEquals($test, $expected);

	}

	/**
	 * @dataProvider getSiteDirImages
	 */
	function dontTestLink($file, $absoluteFile) {

		$test = <<<END
{image src={$file} link="/path/to/something" link-class="link" class="image"}
{image file={$file} href="/path/to/something/else"}
END;

		$expected = <<<END
<a href="/path/to/something" class="link"><img src="$absoluteFile" class="image" width="100" height="75" /></a>
<a href="/path/to/something/else"><img src="$absoluteFile" width="100" height="75" />
END;
	}

	/**
	 * @dataProvider getSiteDirImages
	 */
	function dontTestResize($file, $absoluteFile) {

		$mtime = filemtime($file);
		
		$test = <<<END
{image src="$file" width="10" height="5"}
{image src="$file" width="10" height="5" resize="true"}
END;

		$info = pathinfo($file);

		$expected = <<<END
<img src="/public/images/{$info['basename']}_10_5.{$info['extension']}?$mtime" width="10" height="5" />
END;
	}

	/**
	 * @dataProvider getSiteDirImages
	 */
	function dontTestWeirdAttributes() {
		
		$test = <<<END
{image src="$file" data-something="foo"}
END;

		$expected = <<<END
<img src="$absolute" data-something="foo" />
END;
	}

	function getSiteDirImages() {
			
		$result = array();

		foreach($this->extensions as $ext) {
			$args = array();
			$file = 'images/octopus' . $ext;
			$args[] = $this->getSiteDir() . $file;
			$args[] = '/' . $file;

			$result[] = $args;
		}

		return $result;
	}

	function getTestImages() {
		return $this->testImages;
	}

}

?>