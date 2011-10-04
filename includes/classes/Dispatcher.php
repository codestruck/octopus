<?php

/**
 * Class responsible for locating controllers and rendering views.
 */
class Octopus_Dispatcher {

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
     * @param $response The response being assembled.
     * @return Object An Octopus_Response instance.
     */
    public function handleRequest($request, $response) {

        $path = $request->getResolvedPath();
        $originalPath = $request->getPath();

        if (!($request->getRequestedAction() || $request->getActionArgs())) {

            // No action specified == index, but we need to make sure the
            // path ends with a '/'.
            if (substr($request->getPath(), -1) != '/') {

                $slashUrl = $this->_app->makeUrl('/' . trim($request->getPath(), '/') . '/', $_GET);
                $response->redirect($slashUrl);

                return;
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

        if (!is_array($data)) {
            $data = array('data' => $data);
        }

        // For e.g. 301 redirects we don't need to bother rendering
        if (!$response->shouldContinueProcessing()) {
            return;
        }

        $renderer = $this->_app->createRenderer();

        $renderer->render($controller, $data, $request, $response);
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



    private static function safeRequireOnce($file) {
        require_once($file);
    }


}

?>
