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

    function _default(\$action, \$args) {
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

    function _before_default(\$action, \$args) {
        \$GLOBALS[__METHOD__] = array(\$this->i++, \$args);
    }

    function _after_default(\$action, \$args, \$data) {
        \$GLOBALS[__METHOD__] = array(\$this->i++, \$args);
    }

    function _before_foo(\$args) {
        \$GLOBALS[__METHOD__] = array(\$this->i++, \$args);
    }

    function _after_foo(\$args, \$data) {
        \$GLOBALS[__METHOD__] = array(\$this->i++, \$args);
    }

    function foo(\$arg1, \$arg2) {
        \$GLOBALS[__METHOD__] = array(\$this->i++, \$arg1, \$arg2);
    }

    function _before_missing(\$args) {
        \$GLOBALS[__METHOD__] = array(\$this->i++, \$args);
    }

    function _after_missing(\$args, \$data) {
        \$GLOBALS[__METHOD__] = array(\$this->i++, \$args, \$data);
    }

    function _before_cancel(\$args) {
        \$GLOBALS[__METHOD__] = array(\$this->i++, \$args);
        return false;
    }

    function cancel(\$arg1, \$arg2) {
        \$GLOBALS[__METHOD__] = array(\$this->i++, \$arg1, \$arg2);
    }

    function _after_cancel(\$args, \$data) {
        \$GLOBALS[__METHOD__] = array(\$this->i++, \$args, \$data);
    }

    function emptyAction(\$arg1, \$arg2) {
    \$GLOBALS[__METHOD__] = array(\$this->i++, \$arg1, \$arg2);
    }

    function _before_emptyAction(\$args) {
        \$GLOBALS[__METHOD__] = array(\$this->i++, \$args);
    }

    function _after_emptyAction(\$args, \$data) {
        \$GLOBALS[__METHOD__] = array(\$this->i++, \$args, \$data);
    }



}

?>
END
        );

        $app = $this->startApp();

        $this->createViewFile('_before_and_after/foo');
        $this->createViewFile('_before_and_after/missing');
        $this->createViewFile('_before_and_after/cancel');
        $this->createViewFile('_before_and_after/empty');

        $resp = $app->getResponse('before-and-after/foo/arg1/arg2', true);

        $this->assertEquals(array(0, 'foo', array('arg1', 'arg2')), $GLOBALS['BeforeAndAfterController::_before'], '_before is wrong');
        $this->assertEquals(array(1, array('arg1', 'arg2')), $GLOBALS['BeforeAndAfterController::_before_foo'], '_before_foo is wrong');
        $this->assertEquals(array(2, 'arg1', 'arg2'), $GLOBALS['BeforeAndAfterController::foo'], 'foo is wrong');
        $this->assertEquals(array(3, array('arg1', 'arg2')), $GLOBALS['BeforeAndAfterController::_after_foo'], '_after_foo is wrong');
        $this->assertEquals(array(4, 'foo', array('arg1', 'arg2')), $GLOBALS['BeforeAndAfterController::_after'], '_after is wrong');


        unset($GLOBALS['BeforeAndAfterController::_before']);
        unset($GLOBALS['BeforeAndAfterController::_after']);

        $resp = $app->getResponse('before-and-after/missing/arg1/arg2', true);
        $this->assertEquals(array(0, 'missing', array('arg1', 'arg2')), $GLOBALS['BeforeAndAfterController::_before'], '_before is wrong');
        $this->assertEquals(array(1, array('arg1', 'arg2')), $GLOBALS['BeforeAndAfterController::_before_missing'], '_before_missing is wrong');
        $this->assertEquals(array(2, array('arg1', 'arg2')), $GLOBALS['BeforeAndAfterController::_before_default'], '_before_default is wrong');
        $this->assertEquals(array(3, array('arg1', 'arg2')), $GLOBALS['BeforeAndAfterController::_after_default'], '_after_default is wrong');
        $this->assertEquals(array(4, array('arg1', 'arg2'), null), $GLOBALS['BeforeAndAfterController::_after_missing'], '_after_missing is wrong');
        $this->assertEquals(array(5, 'missing', array('arg1', 'arg2')), $GLOBALS['BeforeAndAfterController::_after'], '_after is wrong');

        unset($GLOBALS['BeforeAndAfterController::_before']);
        unset($GLOBALS['BeforeAndAfterController::_after']);
        unset($GLOBALS['BeforeAndAfterController::cancel']);

        $resp = $app->getResponse('before-and-after/cancel/arg1/arg2', true);
        $this->assertEquals(array(0, 'cancel', array('arg1', 'arg2')), $GLOBALS['BeforeAndAfterController::_before'], '_before is wrong');
        $this->assertEquals(array(1, array('arg1', 'arg2')), $GLOBALS['BeforeAndAfterController::_before_cancel'], '_before_cancel is wrong');
        $this->assertFalse(isset($GLOBALS['BeforeAndAfterController::cancel']), 'cancel should not have been called');
        $this->assertFalse(isset($GLOBALS['BeforeAndAfterController::_after_cancel']), '_after_cancel should not have been called.');
        $this->assertFalse(isset($GLOBALS['BeforeAndAfterController::_after']), '_after should not have been called.');

        unset($GLOBALS['BeforeAndAfterController::_before']);
        unset($GLOBALS['BeforeAndAfterController::_after']);
        unset($GLOBALS['BeforeAndAfterController::_before_emptyAction']);
        unset($GLOBALS['BeforeAndAfterController::_after_emptyAction']);
        unset($GLOBALS['BeforeAndAfterController::emptyAction']);

        $resp = $app->getResponse('before-and-after/empty/arg1/arg2', true);
        $this->assertEquals(array(0, 'empty', array('arg1', 'arg2')), $GLOBALS['BeforeAndAfterController::_before'], '_before is wrong for *Action');
        $this->assertEquals(array(1, array('arg1', 'arg2')), $GLOBALS['BeforeAndAfterController::_before_emptyAction'], '_before_emptyAction is wrong for *Action');
        $this->assertEquals(array(2, 'arg1', 'arg2'), $GLOBALS['BeforeAndAfterController::emptyAction'], 'emptyAction is wrong');
        $this->assertEquals(array(3, array('arg1', 'arg2'), null), $GLOBALS['BeforeAndAfterController::_after_emptyAction'], '_after_emptyAction is wrong');
        $this->assertEquals(array(4, 'empty', array('arg1', 'arg2')), $GLOBALS['BeforeAndAfterController::_after'], '_after is wrong for *Action');

    }

    /**
     * @dataProvider privateAndProtectedPathProvider
     */
    public function testPrivateAndProtectedMethodsNotCallableAsActions($path) {

        $app = $this->startApp();

        global $_PP_TEST_CASE;
        $_PP_TEST_CASE = $this;

        $this->createControllerFile(
            'PrivateAndProtectedActions',
            <<<END
            <?php

            class PrivateAndProtectedActionsController extends Octopus_Controller {

                private function privateAction() {
                    global \$_PP_TEST_CASE;
                    \$_PP_TEST_CASE->assertTrue(false, __METHOD__ . ' was called on the controller!');
                }

                private function privateFoo() {
                    global \$_PP_TEST_CASE;
                    \$_PP_TEST_CASE->assertTrue(false, __METHOD__ . ' was called on the controller!');
                }

                protected function protectedAction() {
                    global \$_PP_TEST_CASE;
                    \$_PP_TEST_CASE->assertTrue(false, __METHOD__ . ' was called on the controller!');
                }

                protected function protectedFoo() {
                    global \$_PP_TEST_CASE;
                    \$_PP_TEST_CASE->assertTrue(false, __METHOD__ . ' was called on the controller!');
                }

            }

            ?>
END
        );
        $this->createViewFile('private_and_protected_actions/private');
        $this->createViewFile('private_and_protected_actions/private_foo');
        $this->createViewFile('private_and_protected_actions/protected');
        $this->createViewFile('private_and_protected_actions/protected_foo');

        $app->getResponse($path, true);

    }

    public static function privateAndProtectedPathProvider() {

        return array(
            array('/private-and-protected-actions/private'),
            array('/private-and-protected-actions/private-foo'),
            array('/private-and-protected-actions/protected'),
            array('/private-and-protected-actions/protected-foo')
        );
    }

    function testBeforeAndAfterNotCallableAsActions() {

        $app = $this->startApp();

        $this->createControllerFile(
            'BeforeAndAfterNotActions',
            <<<END
            <?php
            class BeforeAndAfterNotActionsController extends Octopus_Controller {

                var \$i = 0;

                public function _before(\$action, \$args) {
                    \$GLOBALS[__METHOD__] = array(\$this->i++, \$action, \$args);
                }

                public function _after(\$action, \$args, \$data) {
                    \$GLOBALS[__METHOD__] = array(\$this->i++, \$action, \$args);
                    return \$data;
                }


                public function _before_default(\$action, \$args) {
                    \$GLOBALS[__METHOD__] = array(\$this->i++, \$action, \$args);
                }

                public function _after_default(\$action, \$args, \$data) {
                    \$GLOBALS[__METHOD__] = array(\$this->i++, \$action, \$args);
                    return \$data;
                }


                public function _before_foo(\$args) {
                    \$GLOBALS[__METHOD__] = array(\$this->i++, \$args);
                }

                public function foo(\$arg) {
                    \$GLOBALS[__METHOD__] = array(\$this->i++, \$arg);
                }

                public function _after_foo(\$args, \$data) {
                    \$GLOBALS[__METHOD__] = array(\$this->i++, \$args, \$data);
                    return \$data;
                }


            }
            ?>
END
        );

        $this->createViewFile('before-and-after-not-actions/_before_foo');
        $this->createViewFile('before-and-after-not-actions/_after_foo');
        $this->createViewFile('before-and-after-not-actions/_before');
        $this->createViewFile('before-and-after-not-actions/_after');
        $this->createViewFile('before-and-after-not-actions/_before_default');
        $this->createViewFile('before-and-after-not-actions/_after_default');


        $resp = $app->getResponse('before-and-after-not-actions/_before_foo', true);

        foreach(array('_before_foo', '_after_foo') as $m) {
            $this->assertFalse(isset($GLOBALS['BeforeAndAfterNotActionsController::' . $m]), "$m is set");
        }
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

    function testKeepQuerystringWhenAddingSlash() {

        $app = $this->startApp();
        $this->createControllerFile('KeepQsWhenAddingSlash');


        $tests = array(
            "/keep-qs-when-adding-slash",
        );

        $_GET['foo'] = 'bar';

        foreach($tests as $path) {
            $resp = $app->getResponse($path, true);
            $this->assertEquals(
                <<<END
HTTP/1.1 302 Found
Location: /keep-qs-when-adding-slash/?foo=bar
END
                ,
                trim($resp)
            );
        }


    }


    function testViewNotFoundReturns404() {

        $app = $this->startApp();
        $this->createControllerFile('ViewNotFound404');

        $resp = $app->getResponse('/view-not-found-404/', true);
        $this->assertEquals(404, $resp->getStatus());


    }


}

?>
