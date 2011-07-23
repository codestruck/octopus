<?php

Octopus::loadClass('Octopus_View_Finder');
Octopus::loadClass('Octopus_Renderer');

/**
 * Class responsible for locating controllers and rendering views.
 */
class Octopus_Dispatcher {

    public static $defaultTemplate = 'html/page';

    public static $defaultView = 'default';

    public $debug = false;

    private $_app;

    public function __construct($app = null) {
        $this->_app = ($app ? $app : Octopus_App::singleton());
        $this->debug = $this->_app->isDevEnvironment() && (stripos((isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : ''), '_octopus_debug') !== false);
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

        if (!($request->getRequestedAction() || $request->getActionArgs())) {

            // No action specified == index, but we need to make sure the
            // path ends with a '/'.
            if (substr($request->getPath(), -1) != '/') {

                $slashUrl = $this->_app->makeUrl('/' . trim($request->getPath(), '/') . '/', $_GET);
                $response->redirect($slashUrl);

                return $response;
            }
        }

        $controller = $this->createController($request, $response);

        if (!$controller) {

            if ($this->debug) {
                dump_r('Controller not found for path', $request->getResolvedPath());
            }

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

        // TODO: Does this belong here?
        $this->augmentViewData($data);

        $this->render($controller, $data, $request, $response);

        return $response;
    }

    protected function augmentViewData(&$data) {

        $qs = $_GET;

        $pathArg = $this->_app->getOption('path_querystring_arg');
        unset($qs[$pathArg]);

        $queryString = http_build_query($qs);

        $extra = array(
            'QS' => $queryString,
            'FULL_QS' => ($queryString ? '?' : '') . $queryString,
            'QS_AND' => ($queryString ? '&amp;' : '?')
        );

        foreach($extra as $key => $value) {
            if (!isset($data[$key])) {
                $data[$key] = $value;
            }
        }
    }

    /**
     * Given the controller info array from an Octopus_Nav_Item, instantiates the
     * appropriate controller instance.
     * @return Object An Octopus_Controller if found, otherwise NULL.
     */
    protected function createController($request, $response) {

        $class = $request->getControllerClass();

        $controller = null;

        if (!$class) {
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
        $viewFile = $this->findViewForRender($controller, $request, $response, $data);

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

        if ($this->debug) {

            dump_r('Controller Class', get_class($controller));
            dump_r('Executed Actions', $controller->__getExecutedActions());
            dump_r('View File', $viewFile);

        }

    }

    private function findViewForRender(Octopus_Controller $controller, Octopus_Request $request, Octopus_Response $response, &$data) {

        $finder = new Octopus_View_Finder();

        if ($response->isForbidden()) {
            $info = $finder->findView($request, $controller, 'sys/forbidden');
        } else {
            $info = $finder->findView($request, $controller);
        }

        if ($info && !empty($info['file'])) {

            if (!$info['found']) {

                // View wasn't found, so provide some extra data for the 'view not found' view.
                $data = array(
                    'controller_data' => array(),
                    'path' => $request->getPath(),
                    'resolved_path' => '',
                    'view_paths' => array()
                );

                $response->setStatus(404);

                if ($this->_app->isDevEnvironment()) {
                    $data['controller_data'] = $data;
                    $data['resolved_path'] = $request->getResolvedPath();

                    $paths = $finder->getViewPaths($request, $controller);
                    $paths = preg_replace('/^' . preg_quote(ROOT_DIR, '/') . '/i', '', $paths);
                    $data['view_paths'] = $paths;
                }
            }

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
