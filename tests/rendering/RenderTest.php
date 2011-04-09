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
