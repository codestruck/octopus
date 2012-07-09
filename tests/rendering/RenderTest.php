<?php

/**
 * @group core
 */
class RenderTests extends Octopus_App_TestCase
{
    function __construct() {
        $db = Octopus_DB::singleton();
        $db->query('TRUNCATE settings', true);
        parent::__construct();
    }

    function testBasicViewRendering() {

        $app = $this->startApp();

        $this->createControllerFile('BasicViewRendering', <<<END
<?php

class BasicViewRenderingController extends Octopus_Controller {

	public function action() {

	}

}

END
);

        $views = array(
            "action",
            "basic_view_rendering/action"
        );

        foreach($views as $view) {

            $viewFile = $this->createViewFile($view, $view);
            $resp = $app->getResponse('/basic-view-rendering/action', true);
            $this->assertTrue(!!$resp, "No response returned. Failed on $view");
            $this->assertEquals(200, $resp->getStatus(), '200 status code returned');

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

        $this->createControllerFile('TestBasicSmartyRender', <<<END
<?php

class TestBasicSmartyRenderController extends Octopus_Controller {

	public function action() {

	}

}
END
		);
        $viewFile = $this->createViewFile('action.tpl', 'Smarty view contents');

        $app = $this->startApp();

        $resp = $app->getResponse('/test-basic-smarty-render/action', true);
        $this->assertEquals('Smarty view contents', trim($resp->getContent()));

    }


    function testMustacheViewRendering() {

        $app = $this->startApp();

        $this->createControllerFile('MustacheViewRendering', <<<END
<?php

class MustacheViewRenderingController extends Octopus_Controller {

    public function action() {
        \$foo = '"bar"';
        return compact('foo');
    }

}

END
);

        $viewFile = $this->createViewFile('mustache_view_rendering/action.mustache', 'foo: {{foo}}{{#loop}}ERROR{{/loop}}');
        $resp = $app->getResponse('/mustache-view-rendering/action', true);
        $this->assertTrue(!!$resp, "No response returned");
        $this->assertEquals(200, $resp->getStatus(), '200 status code returned');

        $this->assertEquals(
            'foo: &quot;bar&quot;',
            trim($resp->getContent()),
            "Wrong content for view"
        );

        unlink($viewFile);
    }

    function testMustachePartialRendering() {

        $app = $this->startApp();

        $this->createControllerFile('MustachePartialRendering', <<<END
<?php

class MustachePartialRenderingController extends Octopus_Controller {

    public function action() {
        \$foo = 'bar';
        return compact('foo');
    }

}

END
);

        $viewFile = $this->createViewFile('mustache_partial_rendering/action.mustache', 'outer guy {{> inner}}');
        $partialFile = $this->createViewFile('mustache_partial_rendering/inner.mustache', 'inner guy');
        $resp = $app->getResponse('/mustache-partial-rendering/action', true);
        $this->assertTrue(!!$resp, "No response returned");
        $this->assertEquals(200, $resp->getStatus(), '200 status code returned');

        $this->assertEquals(
            'outer guy inner guy',
            trim($resp->getContent()),
            "Wrong content for view"
        );

        unlink($viewFile);
        unlink($partialFile);
    }

}
