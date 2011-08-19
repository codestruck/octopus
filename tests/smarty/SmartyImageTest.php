<?php

Octopus::loadExternal('smarty');

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

		$s = Octopus_Smarty::singleton();
		$s->reset();
	}

	function tearDown() {
			
		$app = $this->getApp();
		if ($app) {
			$privateDir = $app->getOption('OCTOPUS_PRIVATE_DIR');
			`rm -rf "$privateDir/resize"`;
		}

		parent::tearDown();


	}

	function assertSmartyEquals($expected, $value, $message = '', $replaceMD5 = false, $replaceMtime = false) {
		
		$app = $this->getApp();
		
		$s = Octopus_Smarty::singleton();

		$smartyDir = $this->getSiteDir() . 'smarty/';
		@mkdir($smartyDir);
		
		$tplFile = $smartyDir . 'test.' . md5($expected) . '.tpl';
		@unlink($tplFile);

		file_put_contents($tplFile, $value);

        $s->smarty->template_dir = array($smartyDir);
        $tpl = $s->smarty->createTemplate($tplFile, array());
        
        $rendered = $tpl->fetch();

        if ($replaceMD5) {
        	$rendered = preg_replace('/[a-f\d]{32}/i', '[MD5]', $rendered);
        }

        if ($replaceMtime) {
        	$rendered = preg_replace('/\d{10,}/', '[MTIME]', $rendered);
        }

        $this->assertHtmlEquals($expected, $rendered, $message);
	}

	/**
	 * @dataProvider getSiteDirImages
	 */
	function testPhysicalFileAsSource($file, $fileUrl) {

		foreach(array('src', 'file') as $attr) {
			
			$test = <<<END
{image $attr="$file"}
END;
		
			$mtime = filemtime($file);

			$expected = <<<END
<img src="$fileUrl?$mtime" width="100" height="75" />
END;


			$this->assertSmartyEquals($expected, $test, "$file ($fileUrl), $attr attribute");
		}


	}

	/**
	 * @dataProvider getSiteDirImages
	 */
	function testImageInSiteDir($file, $fileUrl) {

		if (!is_file($file)) {
			$this->markTestIncomplete('Incoming file does not exist!');
			return;
		}

		$urlSiteDir = $this->getSiteDirUrl();
		$name = basename($file);
		
		$test = <<<END
{image src="/images/$name" fail_if_missing="true"}
END;

		$mtime = filemtime($file);

		$expected = <<<END
<img src="{$urlSiteDir}images/$name?$mtime" width="100" height="75" />
END;

		$this->assertSmartyEquals($expected, $test);
	}

	/**
	 * @dataProvider getSiteDirImages
	 */
	function testImageInOctopusDir($file, $fileUrl) {

		$name = basename($file);
		$dir = 'tests/.working/';

		$octopusFile = $this->getOctopusDir() . $dir . $name;
		@unlink($octopusFile);
		copy($file, $octopusFile);

		$octopusUrl = $this->getOctopusDirUrl() . $dir;

		$mtime = filemtime($octopusFile);


		$test = <<<END
{image src="/{$dir}$name"}
END;
		$expected = <<<END
<img src="{$octopusUrl}$name?$mtime" width="100" height="75" />
END;
		$this->assertSmartyEquals($expected, $test);

	}

	/**
	 * @dataProvider getSiteDirImages
	 */
	function testLink($file, $fileUrl) {

		$test = <<<END
{image src={$file} link="/path/to/something" link-class="link" class="image"}
{image file={$file} href="/path/to/something/else"}
END;

		$mtime = filemtime($file);

		$expected = <<<END
<a href="/path/to/something" class="link"><img src="$fileUrl?$mtime" class="image" width="100" height="75" /></a>
<a href="/path/to/something/else"><img src="$fileUrl?$mtime" width="100" height="75" />
END;
	
	}

	/**
	 * @dataProvider getSiteDirImages
	 */
	function testMissingImage($file, $fileUrl) {

		$test = <<<END
{image src="/some/fake/image.png" missing_src="$file" missing_alt="Missing Image"}
END;

		$mtime = filemtime($file);

		$expected = <<<END
<img src="$fileUrl?$mtime" class="missing" width="100" height="75" alt="Missing Image" />
END;
		$this->assertSmartyEquals($expected, $test);
		
	}

	/**
	 * @dataProvider getSiteDirImages
	 */
	function testMissingImageSpan($file, $fileUrl) {
		
		$test = <<<END
{image src="/some/fake/image.png" missing_title="Missing Image"}
END;
		$expected = <<<END
<span class="missing" title="Missing Image" />
END;

		$this->assertSmartyEquals($expected, $test);

	}

	/**
	 * @dataProvider getSiteDirImages
	 */
	function testRelativeToTemplate($file, $fileUrl) {
		
		$smartyImageDir = 'smarty/images/';
		mkdir($this->getSiteDir() . 'smarty/');
		mkdir($this->getSiteDir() . $smartyImageDir);
		$this->assertTrue(is_dir($this->getSiteDir() . $smartyImageDir));

		$name = basename($file);

		$smartyImageFile = $this->getSiteDir() . $smartyImageDir . $name; 

		$this->assertTrue(copy($file, $smartyImageFile), "copy failed ($file to $smartyImageFile)");

		$test = <<<END
{image src="images/$name"}
END;

		$mtime = filemtime($smartyImageFile);
		
		$urlSiteDir = $this->getSiteDirUrl();
		$expected = <<<END
<img src="{$urlSiteDir}{$smartyImageDir}{$name}?{$mtime}" width="100" height="75" />
END;


	}

	/**
	 * @dataProvider getSiteDirImages
	 */
	function testResize($file, $fileUrl) {

		$test = <<<END
{image src="$file" width="10" height="5"}
{image src="$file" width="100" height="75" resize="true"}
{image src="$file" width="10" height="5" resize="true"}
END;

		$info = pathinfo($file);

		$expected = <<<END
<img src="$fileUrl?[MTIME]" width="10" height="5" />
<img src="$fileUrl?[MTIME]" width="100" height="75" />
<img src="/_private/resize/[MTIME]_r_[MD5]_10x5.{$info['extension']}?[MTIME]" width="10" height="5" />
END;

		$this->assertSmartyEquals($expected, $test, '', true, true);
	}



	/**
	 * @dataProvider getSiteDirImages
	 */
	function testWeirdAttributes($file, $fileUrl) {
		
		$test = <<<END
{image src="$file" data_something="foo"}
END;
		$mtime = filemtime($file);
		$expected = <<<END
<img src="$fileUrl?$mtime" data-something="foo" width="100" height="75" />
END;

		$this->assertSmartyEquals($expected, $test);
	}

	/**
	 * @dataProvider getSiteDirImages
	 */
	function testHtmlImageCompatible($file, $fileUrl) {

		$test = <<<END
{image file="$file" _rwidth=10 _rheight=5}
{image file="$file" _rwidth=10 _rheight=5 _r=true}
END;

		$info = pathinfo($file);

		$expected = <<<END
<img src="$fileUrl?[MTIME]" width="100" height="75" />
<img src="/_private/resize/[MTIME]_r_[MD5]_10x5.{$info['extension']}?[MTIME]" width="10" height="5" />
END;

		$this->assertSmartyEquals($expected, $test, '', true, true);
	}

	function getSiteDirImages() {
			
		$result = array();

		foreach($this->extensions as $ext) {
			$args = array();
			$file = 'images/octopus' . $ext;
			$args[] = $this->getSiteDir() . $file;
			$args[] = $this->getSiteDirUrl() . $file;
			$args[] = filemtime($this->getSiteDir() . $file);

			$result[] = $args;
		}

		return $result;
	}



	function getTestImages() {
		return $this->testImages;
	}

}

?>