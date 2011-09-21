<?php

Octopus::loadExternal('smarty');

/**
 * @group smarty
 */
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
			$cacheDir = $app->getOption('OCTOPUS_CACHE_DIR');
			`rm -rf "{$cacheDir}resize"`;
		}

		$octopusDir = $this->getOctopusDir();
		@unlink($octopusDir . 'octopus.gif');
		@unlink($octopusDir . 'octopus.jpg');
		@unlink($octopusDir . 'octopus.png');

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
	function testImageWithUrlBaseJunkAttached($file) {

		$name = basename($file);
		$urlSiteDir = $this->getSiteDirUrl();
		$mtime = filemtime($file);

		$app = $this->getApp();
		$rootDir = $app->getOption('ROOT_DIR');

		$test = <<<END
{image src="/subdir{$urlSiteDir}images/$name" url_base="/subdir/"}
END;
		$expected = <<<END
<img src="/subdir{$urlSiteDir}images/$name?$mtime" width="100" height="75" />
END;

		$this->assertSmartyEquals($expected, $test);

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

		$octopusDir = $this->getOctopusDir();
		$octopusFile = $this->getOctopusDir() . $name;

		@unlink($octopusFile);
		copy($file, $octopusFile);

		$octopusUrl = $this->getOctopusDirUrl();
		$mtime = filemtime($octopusFile);


		$test = <<<END
{image src="{$octopusDir}$name"}
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
	function testMissingImageWithClass($file, $fileUrl) {

		$test = <<<END
{image src="/some/fake/image.png" missing_src="$file" missing_alt="Missing Image" class="jazzy"}
{image src="/some/fake/image.png" default="$file" class="jazzy"}
{image src="/some/fake/image.png" default="$file" class="jazzy jeff" missing_class="mizzing"}
END;

		$mtime = filemtime($file);

		$expected = <<<END
<img src="$fileUrl?$mtime" class="jazzy missing" width="100" height="75" alt="Missing Image" />
<img src="$fileUrl?$mtime" class="jazzy missing" width="100" height="75" />
<img src="$fileUrl?$mtime" class="jazzy jeff mizzing" width="100" height="75" />
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

		// NOTE: Octopus_Image_Mode_Resize does not let you resize something w/ a different
		// aspect ratio, so resizing a 100x75 image to 10x5 results in a 6x5 image.

		$expected = <<<END
<img src="$fileUrl?[MTIME]" width="10" height="5" />
<img src="$fileUrl?[MTIME]" width="100" height="75" />
<img src="/cache/smarty_image/[MTIME]_[MD5]_r_10x5_.{$info['extension']}?[MTIME]" width="6" height="5" />
END;

		$this->assertSmartyEquals($expected, $test, '', true, true);
	}

	/**
	 * @dataProvider getSiteDirImages
	 */
	function testCrop($file, $fileUrl) {

		$test = <<<END
{image src="$file" width="10" height="5"}
{image src="$file" width="100" height="75" crop="true"}
{image src="$file" width="10" height="5" crop="true"}
END;

		$info = pathinfo($file);

		$expected = <<<END
<img src="$fileUrl?[MTIME]" width="10" height="5" />
<img src="$fileUrl?[MTIME]" width="100" height="75" />
<img src="/cache/smarty_image/[MTIME]_[MD5]_c_10x5_.{$info['extension']}?[MTIME]" width="10" height="5" />
END;

		$this->assertSmartyEquals($expected, $test, '', true, true);
	}

	/**
	 * @dataProvider getSiteDirImages
	 */
	function testActionsEqualsCrop($file, $fileUrl) {

		$actions = array('c', 'crop');

		foreach($actions as $action) {

			$test = <<<END
{image src="$file" width="10" height="5"}
{image src="$file" width="100" height="75" action="$action"}
{image src="$file" width="10" height="5" action="$action"}
END;

			$info = pathinfo($file);

			$actionFileName = $action[0];

			$expected = <<<END
<img src="$fileUrl?[MTIME]" width="10" height="5" />
<img src="$fileUrl?[MTIME]" width="100" height="75" />
<img src="/cache/smarty_image/[MTIME]_[MD5]_{$actionFileName}_10x5_.{$info['extension']}?[MTIME]" width="10" height="5" />
END;

			$this->assertSmartyEquals($expected, $test, '', true, true);
		}
	}

	/**
	 * @dataProvider getSiteDirImages
	 */
	function testActionsEqualsResize($file, $fileUrl) {

		$actions = array('r', 'resize');

		foreach($actions as $action) {

			$test = <<<END
{image src="$file" width="10" height="5"}
{image src="$file" width="100" height="75" action="$action"}
{image src="$file" width="10" height="5" action="$action"}
END;

			$info = pathinfo($file);

		// NOTE: Octopus_Image_Mode_Resize does not let you resize something w/ a different
		// aspect ratio, so resizing a 100x75 image to 10x5 results in a 6x5 image.

			$actionFileName = $action[0];

			$expected = <<<END
<img src="$fileUrl?[MTIME]" width="10" height="5" />
<img src="$fileUrl?[MTIME]" width="100" height="75" />
<img src="/cache/smarty_image/[MTIME]_[MD5]_{$actionFileName}_10x5_.{$info['extension']}?[MTIME]" width="6" height="5" />
END;

			$this->assertSmartyEquals($expected, $test, '', true, true);
		}
	}

	/**
	 * @dataProvider getSiteDirImages
	 */
	function testBlankHrefDoesntRenderLink($file, $fileUrl) {

		$test = <<<END
{image src="$file" href=""}
{image src="$file" href="  "}
END;
		$mtime = filemtime($file);
		$expected = <<<END
<img src="$fileUrl?$mtime" width="100" height="75" />
<img src="$fileUrl?$mtime" width="100" height="75" />
END;

		$this->assertSmartyEquals($expected, $test);
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
<img src="/cache/smarty_image/[MTIME]_[MD5]_r_10x5_.{$info['extension']}?[MTIME]" width="6" height="5" />
END;

		$this->assertSmartyEquals($expected, $test, '', true, true);
	}

	/**
	 * @dataProvider getSiteDirImages
	 */
	function testHtmlImageCompatibleDefault($file, $fileUrl) {

		$test = <<<END
{image file="/some/fake/image.png" default="$file"}
END;

		$info = pathinfo($file);

		$expected = <<<END
<img src="$fileUrl?[MTIME]" class="missing" width="100" height="75" />
END;

		$this->assertSmartyEquals($expected, $test, '', true, true);
	}

	/**
	 * @dataProvider getSiteDirImages
	 */
	function testHtmlImageCompatibleIgnoreDims($file, $fileUrl) {

		$test = <<<END
{image file="$file" ignoredims=true}
{image file="$file" ignoredims=false}
END;

		$expected = <<<END
<img src="$fileUrl?[MTIME]" />
<img src="$fileUrl?[MTIME]" width="100" height="75" />
END;

		$this->assertSmartyEquals($expected, $test, '', true, true);

	}

	/**
	 * @dataProvider getSiteDirImages
	 */
	function testResizeConstrained($file, $fileUrl) {

		$constraints = array(
			'width' => array(
				'width' => 50,
				'height' => 37
			),
			'height' => array(
				'width' => 13,
				'height' => 10,
			)
		);
		$constraints['w'] = $constraints['width'];
		$constraints['h'] = $constraints['height'];

		$info = pathinfo($file);

		foreach($constraints as $constrain => $dims) {

			$constrainFileName = $constrain[0];

			$test = <<<END
{image file="$file" width="50" height="10" constrain="$constrain" resize="true"}
END;
			$expected = <<<END
<img src="/cache/smarty_image/[MTIME]_[MD5]_r_50x10_{$constrainFileName}.{$info['extension']}?[MTIME]" width="{$dims['width']}" height="{$dims['height']}" />
END;

			$this->assertSmartyEquals($expected, $test, "constrain: $constrain", true, true);
		}

	}



	function getSiteDirImages() {

		$result = array();

		foreach($this->extensions as $ext) {

			$args = array();
			$file = 'images/octopus' . $ext;

			$args[] = $this->getSiteDir() . $file;
			$args[] = $this->getSiteDirUrl() . $file;

			if (is_file($this->getSiteDir() . $file)) {
				$args[] = filemtime($this->getSiteDir() . $file);
			}

			$result[] = $args;
		}

		return $result;
	}



	function getTestImages() {
		return $this->testImages;
	}

}

?>
