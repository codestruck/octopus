<?php

/**
 * @group core
 */
class RenderTests extends Octopus_App_TestCase
{

    function testBasicViewRendering() {

        $app = $this->startApp();

        $this->createControllerFile('Foo');

        $views = array(
            "action",
            "foo/action"
        );

        foreach($views as $view) {

            $viewFile = $this->createViewFile($view, $view);

            $resp = $app->getResponse('/foo/action', true);
            $this->assertTrue(!!$resp, "No response returned. Failed on $view");

            $this->assertEquals(
                $view,
                trim($resp->getContent()),
                "Wrong content for view: $view"
            );

            unlink($viewFile);
        }
    }

    function testRenderingWithExistingController() {

        $app = $this->startApp();

        $this->createControllerFile('RenderExisting');
        $this->createViewFile('render-existing/foo', 'SUCCESS!');

        $resp = $app->getResponse('render-existing/foo', true);
        $this->assertEquals('SUCCESS!', $resp->getContent());

    }


    function testBasicSmartyViewRendering() {

        $app = $this->startApp();

        $this->createControllerFile('TestBasicSmartyRender');
        $viewFile = $this->createViewFile('action.tpl', 'Smarty view contents');

        $app = $this->startApp();

        $resp = $app->getResponse('/test-basic-smarty-render/action', true);
        $this->assertTrue(!!$resp, 'No response returned.');

        $this->assertEquals('Smarty view contents', trim($resp->getContent()));

    }

}

?>
