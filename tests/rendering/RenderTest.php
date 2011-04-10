<?php

SG::loadClass('SG_App');

/**
 * @group core
 */
class RenderTests extends PHPUnit_Framework_TestCase
{
    static $siteDir = 'render-tests';


    function cleanUpSiteDir() {
        $siteDir = self::$siteDir;
        `rm -rf {$siteDir}`;
    }

    function setUpSiteDir() {

        $this->cleanUpSiteDir();

        $siteDir = self::$siteDir;

        mkdir($siteDir);
        mkdir("$siteDir/views");
        mkdir("$siteDir/views/controller");
        mkdir("$siteDir/controllers");
        mkdir("$siteDir/content");
        mkdir("$siteDir/themes");
        mkdir("$siteDir/themes/default");
        mkdir("$siteDir/themes/default/templates");
        mkdir("$siteDir/themes/default/templates/html");

        touch("$siteDir/config.php");
        touch("$siteDir/nav.php");

        file_put_contents("$siteDir/themes/default/templates/html/page.php", '<?php echo $view_content; ?>');

    }


    function testBasicViewRendering() {

        $this->setUpSiteDir();
        $siteDir = self::$siteDir;

        $locations = array(
            "$siteDir/views/action.php",
            "$siteDir/views/controller/action.php"
        );

        foreach($locations as $viewLoc) {

            file_put_contents(
                $viewLoc,
                <<<END
$viewLoc
END
            );

            $app = SG_App::start(array(
                'SITE_DIR' => $siteDir
            ));

            $app->getNav()
                ->add('controller/action');

            $resp = $app->getResponse('/controller/action');
            $this->assertTrue(!!$resp, 'No response returned. loc: ' . $viewLoc);

            $this->assertEquals(
                $viewLoc,
                trim($resp->getContent()),
                "Wrong content for view: $viewLoc"
            );

            unlink($viewLoc);
        }
    }

    function testRenderingWithExistingController() {

        $this->setUpSiteDir();
        $siteDir = self::$siteDir;

        mkdir("$siteDir/views/test");

        file_put_contents(
            "$siteDir/controllers/test.php",
            <<<END
<?php
class TestController extends SG_Controller {}
?>
END
        );

        file_put_contents(
            "$siteDir/views/test/foo.php",
            "SUCCESS!"
        );

        $app = SG_App::start(array(
            'SITE_DIR' => $siteDir
        ));

        $resp = $app->getResponse('test/foo');
        $this->assertEquals('SUCCESS!', $resp->getContent());

    }

    function testSysControllerNotAvailableOutsideDev() {

        $siteDir = self::$siteDir;
        @mkdir("$siteDir/views/sys");
        touch("$siteDir/views/sys/forbidden.php");

        $states = array(
            'DEV' => true,
            'LIVE' => false,
            'STAGING' => false
        );

        foreach($states as $state => $available) {

            $app = SG_App::start(array(
                'use_defines' => false,
                'use_globals' => false,
                $state => true,
                'SITE_DIR' => self::$siteDir
            ));

            $resp = $app->getResponse('sys/about');

            if ($available) {
                $this->assertEquals(200, $resp->getStatus(), "sys/about should be available under $state");
            } else {
                $this->assertEquals(403, $resp->getStatus(), "should be forbidden under $state");
                $this->assertEquals('', $resp->getContent(), "content should be empty under $state");
            }
        }

    }

    function dontTestBasicSmartyViewRendering() {

        $this->setUpSiteDir();
        $siteDir = self::$siteDir;

        file_put_contents(
            "$siteDir/views/action.php",
            <<<END
Basic view contents.
END
        );

        $app = SG_App::start(array(
            'SITE_DIR' => $siteDir
        ));

        $app->getNav()
            ->add('controller/action');

        $resp = $app->getResponse('/controller/action');
        $this->assertTrue(!!$resp, 'No response returned.');

        $this->assertEquals(
            'Basic view contents.',
            trim($resp->getContent())
        );

    }

}

?>
