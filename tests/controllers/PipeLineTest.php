<?php

/**
 * Tests of the execution/rendering pipeline.
 */
class PipelineTests extends Octopus_App_TestCase {

    function testDefaultActionReceivesActionAndArgs() {

        file_put_contents(
            "{$this->siteDir}/controllers/DefaultAction.php",
            <<<END
<?php

class DefaultActionController extends Octopus_Controller {

    function defaultAction(\$action, \$args) {
        \$GLOBALS['action:' . \$action] = \$args;
    }

}

?>
END
        );


        $app = $this->startApp();

        $this->createViewFile('default_action/foo');

        unset($GLOBALS['action:foo']);
        $response = $app->getResponse('default-action/foo/arg1/arg2', true);

        $this->assertEquals(
            array('arg1', 'arg2'),
            $GLOBALS['action:foo']
        );

    }

    function testBeforeAndAfterActionsCalled() {

        file_put_contents(
            "{$this->siteDir}/controllers/BeforeAndAfter.php",
            <<<END
<?php

class BeforeAndAfterController extends Octopus_Controller {

    var \$i = 0;

    function _before(\$action, \$args) {
        \$GLOBALS[__METHOD__] = array(\$this->i++, \$action, \$args);
    }

    function _after(\$action, \$args, \$data) {
        \$GLOBALS[__METHOD__] = array(\$this->i++, \$action, \$args);
    }

    function before_defaultAction(\$action, \$args) {
        \$GLOBALS[__METHOD__] = array(\$this->i++, \$args);
    }

    function after_defaultAction(\$action, \$args, \$data) {
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

        $app = $this->startApp();

        $this->createViewFile('before_and_after/foo');
        $this->createViewFile('before_and_after/missing');
        $this->createViewFile('before_and_after/cancel');

        $resp = $app->getResponse('before-and-after/foo/arg1/arg2', true);

        $this->assertEquals(array(0, 'foo', array('arg1', 'arg2')), $GLOBALS['BeforeAndAfterController::_before'], '_before is wrong');
        $this->assertEquals(array(1, array('arg1', 'arg2')), $GLOBALS['BeforeAndAfterController::before_foo'], 'before_foo is wrong');
        $this->assertEquals(array(2, 'arg1', 'arg2'), $GLOBALS['BeforeAndAfterController::foo'], 'foo is wrong');
        $this->assertEquals(array(3, array('arg1', 'arg2')), $GLOBALS['BeforeAndAfterController::after_foo'], 'after_foo is wrong');
        $this->assertEquals(array(4, 'foo', array('arg1', 'arg2')), $GLOBALS['BeforeAndAfterController::_after'], '_after is wrong');


        unset($GLOBALS['BeforeAndAfterController::_before']);
        unset($GLOBALS['BeforeAndAfterController::_after']);

        $resp = $app->getResponse('before-and-after/missing/arg1/arg2', true);
        $this->assertEquals(array(0, 'missing', array('arg1', 'arg2')), $GLOBALS['BeforeAndAfterController::_before'], '_before is wrong');
        $this->assertEquals(array(1, array('arg1', 'arg2')), $GLOBALS['BeforeAndAfterController::before_missing'], 'before_missing is wrong');
        $this->assertEquals(array(2, array('arg1', 'arg2')), $GLOBALS['BeforeAndAfterController::before_defaultAction'], 'before_defaultAction is wrong');
        $this->assertEquals(array(3, array('arg1', 'arg2')), $GLOBALS['BeforeAndAfterController::after_defaultAction'], 'after_defaultAction is wrong');
        $this->assertEquals(array(4, array('arg1', 'arg2'), null), $GLOBALS['BeforeAndAfterController::after_missing'], 'after_missing is wrong');
        $this->assertEquals(array(5, 'missing', array('arg1', 'arg2')), $GLOBALS['BeforeAndAfterController::_after'], '_after is wrong');

        unset($GLOBALS['BeforeAndAfterController::_before']);
        unset($GLOBALS['BeforeAndAfterController::_after']);
        unset($GLOBALS['BeforeAndAfterController::cancel']);

        $resp = $app->getResponse('before-and-after/cancel/arg1/arg2', true);
        $this->assertEquals(array(0, 'cancel', array('arg1', 'arg2')), $GLOBALS['BeforeAndAfterController::_before'], '_before is wrong');
        $this->assertEquals(array(1, array('arg1', 'arg2')), $GLOBALS['BeforeAndAfterController::before_cancel'], 'before_cancel is wrong');
        $this->assertFalse(isset($GLOBALS['BeforeAndAfterController::cancel']), 'cancel should not have been called');
        $this->assertFalse(isset($GLOBALS['BeforeAndAfterController::after_cancel']), 'after_cancel should not have been called.');
        $this->assertFalse(isset($GLOBALS['BeforeAndAfterController::_after']), '_after should not have been called.');
    }

    function testRedirectToAddSlashOnIndex() {

        $app = $this->startApp();

        $this->createControllerFile('AddSlashTest');
        $this->createViewFile('add_slash_test/index');

        $resp = $app->getResponse('/add-slash-test', true);

        $this->assertEquals(
            <<<END
HTTP/1.1 302 Found
Location: /add-slash-test/
END
            ,
            trim($resp)
        );

        $resp = $app->getResponse('/add-slash-test/', true);
        $this->assertEquals(200, $resp->getStatus());

    }



}

?>
