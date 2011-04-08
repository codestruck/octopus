<?php

SG::loadClass('SG_App');

/**
 * @group core
 */
class RenderTests extends PHPUnit_Framework_TestCase
{
    static $siteDir = 'render-tests';


    function cleanUpSiteDir() {
        //`rm -rf {self::$siteDir}`;
    }

    function setUpSiteDir() {

        $this->cleanUpSiteDir();

        $siteDir = self::$siteDir;

        mkdir($siteDir);
        mkdir("$siteDir/views");
        mkdir("$siteDir/controllers");
        mkdir("$siteDir/content");
        mkdir("$siteDir/themes");
        mkdir("$siteDir/themes/default");

    }

    function testNothing() {
        $this->assertTrue(true);
    }

    function dontTestBasicViewRendering() {

        $this->setUpSiteDir();

        file_put_contents(
            "$siteDir/views/action.php",
            <<<END
Basic view contents.
END
        );

        $app = SG_App::start(array(
            'SITE_DIR' => self::$siteDir
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
