<?php

Octopus::loadClass('Octopus_App');

/**
 * @group core
 */
class RenderTests extends Octopus_App_TestCase
{

    function testBasicViewRendering() {

        mkdir("{$this->siteDir}/views/controller");

        $locations = array(
            "{$this->siteDir}/views/action.php",
            "{$this->siteDir}/views/controller/action.php"
        );

        foreach($locations as $viewLoc) {

            file_put_contents(
                $viewLoc,
                <<<END
$viewLoc
END
            );

            $app = $this->startApp();

            $resp = $app->getResponse('/controller/action', true);
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

        mkdir("{$this->siteDir}/views/test");

        file_put_contents(
            "{$this->siteDir}/controllers/test.php",
            <<<END
<?php
class TestController extends Octopus_Controller {}
?>
END
        );

        file_put_contents(
            "{$this->siteDir}/views/test/foo.php",
            "SUCCESS!"
        );

        $app = $this->startApp();

        $resp = $app->getResponse('test/foo', true);
        $this->assertEquals('SUCCESS!', $resp->getContent());

    }


    function testBasicSmartyViewRendering() {

        file_put_contents(
            "{$this->siteDir}/views/action.tpl",
            <<<END
Basic view contents.
END
        );

        $app = $this->startApp();

        $resp = $app->getResponse('/controller/action', true);
        $this->assertTrue(!!$resp, 'No response returned.');

        $this->assertEquals(
            'Basic view contents.',
            trim($resp->getContent())
        );

    }

}

?>
