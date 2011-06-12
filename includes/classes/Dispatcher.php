<?php

Octopus::loadClass('Octopus_Renderer');

/**
 * Class responsible for locating controllers and rendering views.
 */
class Octopus_Dispatcher {

    public static $defaultTemplate = 'html/page';

    public static $defaultView = 'default';

    private $_app;

    public function __construct($app = null) {
        $this->_app = ($app ? $app : Octopus_App::singleton());
    }

    /**
     * Given an Octopus_Request, renders the page and generates an
     * Octopus_Response instance for it.
     * @param $request Object An Octopus_Request instance.
     * @return Object An Octopus_Response instance.
     */
    public function &getResponse($request, $buffer = false) {

        $path = $request->getResolvedPath();
        $originalPath = $request->getPath();

        $response = new Octopus_Response($buffer);

        if (!$request->getRequestedAction()) {

            // No action specified == index, but we need to make sure the
            // path ends with a '/'.
            if (substr($request->getPath(), -1) != '/') {

                $slashUrl = $this->_app->makeUrl('/' . trim($request->getPath(), '/') . '/');
                $response->redirect($slashUrl);

                return $response;
            }
        }

        $controller = $this->createController($request, $response);

        if (!$controller) {
            $controller = $this->createDefaultController($request, $response);
        }

        $data = $controller->__execute(
            $request->getAction(),
            $request->getActionArgs()
        );

        // For e.g. 301 redirects we don't need to bother rendering
        if (!$response->shouldContinueProcessing()) {
            return $response;
        }

        $this->render($controller, $data, $request, $response);

        return $response;
    }

    /**
     * Given the controller info array from an Octopus_Nav_Item, instantiates the
     * appropriate controller instance.
     * @return Object An Octopus_Controller if found, otherwise NULL.
     */
    protected function &createController($request, $response) {

        $class = $request->getControllerClass();

        $controller = null;

        if (!$class) {
            // Requested class does not exist
            $response->notFound();
            app_error("Controller class does not exist: " . $class);
            return false;
        }

        $controller = new $class();
        $this->configureController($controller, $request, $response);

        return $controller;

    }

    private function requireOnce($file) {
        require_once($file);
    }

    protected function &createDefaultController($request, $response) {

        $this->requireOnce($this->_app->getOption('OCTOPUS_DIR') . 'controllers/Default.php');

        $controller = new DefaultController();
        $this->configureController($controller, $request, $response);

        return $controller;
    }

    private function configureController($controller, $request, $response) {

        $controller->app = $this->_app;
        $controller->request = $request;
        $controller->response = $response;

        $controller->template = null;
        $controller->view = null;


    }

    /**
     * Renders out the result of an action.
     * @param $controller An Octopus_Controller instance.
     * @param $data Array The result of executing the action on the controller.
     * @param $request Octopus_Request
     * @param $response Octopus_Response
     */
    protected function render($controller, $data, $request, $response) {

        $templateFile = $this->findTemplateForRender($controller, $request);
        $viewFile = $this->findViewForRender($controller, $request, $response);

        $viewContent = $templateContent = '';

        if ($viewFile) {
            $viewRenderer = Octopus_Renderer::createForFile($viewFile);
            $viewContent = $viewRenderer->render($data);
        } else {
            // TODO handle view not found
            return;
            //die("View not found: $view");
        }

        if ($templateFile) {
            $templateRenderer = Octopus_Renderer::createForFile($templateFile);
            $data['view_content'] = $viewContent;
            $templateContent = $templateRenderer->render($data);
        } else {
            // TODO handle template not found
            return;
            // die("Template not found: $template");
        }

        $response->append($templateContent);

    }

    private function findViewForRender($controller, $request, $response) {

        if ($response->isForbidden()) {
            $info = $this->_app->findView('sys/forbidden');
        } else if (!empty($controller->view)) {
            $info = $this->_app->findView($controller->view);
        } else {
            $info = $this->_app->findView($request);
        }

        if ($info && !empty($info['file'])) {
            return $info['file'];
        }

        return false;
    }

    private function findTemplateForRender($controller, $request) {

        $siteDir = $this->_app->getOption('SITE_DIR');
        $octopusDir = $this->_app->getOption('OCTOPUS_DIR');

        $extensions = array('', '.php', '.tpl');
        $theme = $this->_app->getTheme($request);

        $template = $controller->template;

        if ($template === null) {
            $template = self::$defaultTemplate;
        }

        if (strncmp($template, '/', 1) != 0) {
            $template = $this->_app->getFile(
                $template,
                array($siteDir . 'themes/' . $theme . '/templates/', $octopusDir . 'themes/' . $theme . '/templates/'),
                array(
                    'extensions' => $extensions
                )
            );
        }

        if (!$template) return $template;

        foreach($extensions as $ext) {
            $f = $template . $ext;
            if (is_file($f)) {
                return $f;
            }
        }

        return $template;
    }

    private static function safeRequireOnce($file) {
        require_once($file);
    }


}

?>
