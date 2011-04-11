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



    function testBeforeAndAfterActionsCalled() {

        $siteDir = self::$siteDir;
        file_put_contents(
            "$siteDir/controllers/BeforeAndAfter.php",
            <<<END
<?php

class BeforeAndAfterController extends SG_Controller {

    var \$i = 0;

    function _before(\$action, \$args) {
        \$GLOBALS[__METHOD__] = array(\$this->i++, \$action, \$args);
    }

    function _after(\$action, \$args, \$data) {
        \$GLOBALS[__METHOD__] = array(\$this->i++, \$action, \$args);
    }

    function before_defaultAction(\$args) {
        \$GLOBALS[__METHOD__] = array(\$this->i++, \$args);
    }

    function after_defaultAction(\$args, \$data) {
        \$GLOBALS[__METHOD__] = array(\$this->i++, \$args);
    }

    function before_foo(\$args) {
        \$GLOBALS[__METHOD__] = array(\$this->i++, \$args);
    }

    function after_foo(\$args, \$data) {
        \$GLOBALS[__METHOD__] = array(\$this->i++, \$args);
    }

    function foo(\$arg1, \$arg2) {
        \$GLOBALS[__METHOD__] = array(\$this->i++, \$arg1, \$arg2);
    }

    function before_missing(\$args) {
        \$GLOBALS[__METHOD__] = array(\$this->i++, \$args);
    }

    function after_missing(\$args, \$data) {
        \$GLOBALS[__METHOD__] = array(\$this->i++, \$args, \$data);
    }

    function before_cancel(\$args) {
        \$GLOBALS[__METHOD__] = array(\$this->i++, \$args);
        return false;
    }

    function cancel(\$arg1, \$arg2) {
        \$GLOBALS[__METHOD__] = array(\$this->i++, \$arg1, \$arg2);
    }

    function after_cancel(\$args, \$data) {
        \$GLOBALS[__METHOD__] = array(\$this->i++, \$args, \$data);
    }

}

?>
END
        );

        $app = SG_App::start(array(
            'SITE_DIR' => $siteDir
        ));

        $resp = $app->getResponse('before-and-after/foo/arg1/arg2');

        $this->assertEquals(array(0, 'foo', array('arg1', 'arg2')), $GLOBALS['BeforeAndAfterController::_before'], '_before is wrong');
        $this->assertEquals(array(1, array('arg1', 'arg2')), $GLOBALS['BeforeAndAfterController::before_foo'], 'before_foo is wrong');
        $this->assertEquals(array(2, 'arg1', 'arg2'), $GLOBALS['BeforeAndAfterController::foo'], 'foo is wrong');
        $this->assertEquals(array(3, array('arg1', 'arg2')), $GLOBALS['BeforeAndAfterController::after_foo'], 'after_foo is wrong');
        $this->assertEquals(array(4, 'foo', array('arg1', 'arg2')), $GLOBALS['BeforeAndAfterController::_after'], '_after is wrong');


        unset($GLOBALS['BeforeAndAfterController::_before']);
        unset($GLOBALS['BeforeAndAfterController::_after']);

        $resp = $app->getResponse('before-and-after/missing/arg1/arg2');
        $this->assertEquals(array(0, 'missing', array('arg1', 'arg2')), $GLOBALS['BeforeAndAfterController::_before'], '_before is wrong');
        $this->assertEquals(array(1, array('arg1', 'arg2')), $GLOBALS['BeforeAndAfterController::before_missing'], 'before_missing is wrong');
        $this->assertEquals(array(2, array('arg1', 'arg2')), $GLOBALS['BeforeAndAfterController::before_defaultAction'], 'before_defaultAction is wrong');
        $this->assertEquals(array(3, array('arg1', 'arg2')), $GLOBALS['BeforeAndAfterController::after_defaultAction'], 'after_defaultAction is wrong');
        $this->assertEquals(array(4, array('arg1', 'arg2'), null), $GLOBALS['BeforeAndAfterController::after_missing'], 'after_missing is wrong');
        $this->assertEquals(array(5, 'missing', array('arg1', 'arg2')), $GLOBALS['BeforeAndAfterController::_after'], '_after is wrong');

        unset($GLOBALS['BeforeAndAfterController::_before']);
        unset($GLOBALS['BeforeAndAfterController::_after']);
        unset($GLOBALS['BeforeAndAfterController::cancel']);

        $resp = $app->getResponse('before-and-after/cancel/arg1/arg2');
        $this->assertEquals(array(0, 'cancel', array('arg1', 'arg2')), $GLOBALS['BeforeAndAfterController::_before'], '_before is wrong');
        $this->assertEquals(array(1, array('arg1', 'arg2')), $GLOBALS['BeforeAndAfterController::before_cancel'], 'before_cancel is wrong');
        $this->assertFalse(isset($GLOBALS['BeforeAndAfterController::cancel']), 'cancel should not have been called');
        $this->assertFalse(isset($GLOBALS['BeforeAndAfterController::after_cancel']), 'after_cancel should not have been called.');
        $this->assertFalse(isset($GLOBALS['BeforeAndAfterController::_after']), '_after should not have been called.');
    }

    function testBasicSmartyViewRendering() {

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
