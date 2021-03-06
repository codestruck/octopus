<?php

Octopus::loadExternal('smarty');

/**
 * @group smarty
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
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

    /**
     * @dataProvider getSiteDirImages
     */
    function testDontEscapeLinkAttributes($file, $fileUrl) {

        $mtime = filemtime($file);

        $test = <<<END
{image src="$file" alt="&lt;Test&#039;&gt;" href="&gt;test"}
END;
        $expected = <<<END
<a href="&gt;test"><img src="$fileUrl?$mtime" width="100" height="75" alt="&lt;Test&#039;&gt;" /></a>
END;

        $this->assertSmartyEquals($expected, $test);

    }

    function testDontEscapeMissingAttributes() {

        $test = <<<END
{image src="/some/fake/file.jpg" missing_title="&gt;&lt;"}
END;
        $expected = <<<END
<span class="missing" title="&gt;&lt;" />
END;

        $this->assertSmartyEquals($expected, $test);

    }

    /**
     * @dataProvider getSiteDirImages
     */
    function testDontEscapeAttributes($file, $fileUrl) {

        $mtime = filemtime($file);

        $test = <<<END
{image src="$file" alt="&lt;Test&#039;&gt;"}
END;
        $expected = <<<END
<img src="$fileUrl?$mtime" width="100" height="75" alt="&lt;Test&#039;&gt;" />
END;

        $this->assertSmartyEquals($expected, $test);

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
<img src="/cache/smarty_image/[MTIME]_[MD5]_r_10x5_.{$info['extension']}" width="6" height="5" />
END;

        $this->assertSmartyEquals($expected, $test, '', true, true);
    }

    /**
     * @group slow
     * @dataProvider getSiteDirImages
     */
    function testResizeUsesCache($file, $fileUrl) {

        $test = <<<END
{image src="$file" width="10" height="5" resize="true"}
END;

        $info = pathinfo($file);

        // NOTE: Octopus_Image_Mode_Resize does not let you resize something w/ a different
        // aspect ratio, so resizing a 100x75 image to 10x5 results in a 6x5 image.
        clearstatcache();
        $mtime = filemtime($file);
        $expected = <<<END
<img src="/cache/smarty_image/{$mtime}_[MD5]_r_10x5_.{$info['extension']}" width="6" height="5" />
END;

        $this->assertSmartyEquals($expected, $test, '', true);

        $md5 = md5($file);
        $resizedMTime = filemtime($this->getCacheDir() . "smarty_image/{$mtime}_{$md5}_r_10x5_.{$info['extension']}");
        $this->assertTrue(!!$resizedMTime, 'get mtime of cache file');

        sleep(2);
        clearstatcache();

        $this->assertSmartyEquals($expected, $test, '', true);

        $this->assertEquals($resizedMTime, filemtime($this->getCacheDir() . "smarty_image/{$mtime}_{$md5}_r_10x5_.{$info['extension']}"), 'mtime on cache file not changed');

        sleep(2);

        touch($file);
        clearstatcache();
        $mtime = filemtime($file);

        $expected = <<<END
<img src="/cache/smarty_image/{$mtime}_[MD5]_r_10x5_.{$info['extension']}" width="6" height="5" />
END;

        $this->assertSmartyEquals($expected, $test, '', true);


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
<img src="/cache/smarty_image/[MTIME]_[MD5]_c_10x5_.{$info['extension']}" width="10" height="5" />
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
<img src="/cache/smarty_image/[MTIME]_[MD5]_{$actionFileName}_10x5_.{$info['extension']}" width="10" height="5" />
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
<img src="/cache/smarty_image/[MTIME]_[MD5]_{$actionFileName}_10x5_.{$info['extension']}" width="6" height="5" />
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
<img src="/cache/smarty_image/[MTIME]_[MD5]_r_10x5_.{$info['extension']}" width="6" height="5" />
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
{image file="$file" ignoredims="true"}
{image file="$file" ignoredims=1}
{image file="$file" ignoredims='true'}
END;

        $expected = <<<END
<img src="$fileUrl?[MTIME]" />
<img src="$fileUrl?[MTIME]" width="100" height="75" />
<img src="$fileUrl?[MTIME]" />
<img src="$fileUrl?[MTIME]" />
<img src="$fileUrl?[MTIME]" />
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
<img src="/cache/smarty_image/[MTIME]_[MD5]_r_50x10_{$constrainFileName}.{$info['extension']}" width="{$dims['width']}" height="{$dims['height']}" />
END;

            $this->assertSmartyEquals($expected, $test, "constrain: $constrain", true, true);
        }

    }

    function testRemoteImageNoAction() {

        $remoteImage = "http://www.google.com/intl/en_com/images/srpr/logo3w.png";

        $test = "{image file=\"$remoteImage\"}";
        $expected = "<img src=\"$remoteImage\" />";

        $this->assertSmartyEquals($expected, $test);

    }

    /**
     * @group slow
     */
    function testRemoteImageResize() {

        $remoteImage = "http://www.google.com/intl/en_com/images/srpr/logo3w.png";

        $test = "{image file=\"$remoteImage\" width=100 action=resize}";

        $expected = <<<END
<img src="/cache/smarty_image/[MTIME]_[MD5]_r_100x35_.png" width="101" height="35" />
END;

        $this->assertSmartyEquals($expected, $test, 'remote image resize', true, true);
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
