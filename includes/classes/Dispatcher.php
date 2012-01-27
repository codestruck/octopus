<?php

/**
 * Class responsible for locating controllers and rendering views.
 */
class Octopus_Dispatcher {

    private $app;

    public function __construct(Octopus_App $app) {
        $this->app = $app;
    }

    /**
     * Executes the action(s) for a request and renders the resolt.
     */
    public function handleRequest(Octopus_Request $request, Octopus_Response $response) {

        $path = $request->getResolvedPath();

        if (!($request->getRequestedAction() || $request->getActionArgs())) {

            // No action specified == index, but we need to make sure the
            // path ends with a '/'.
            if (substr($request->getPath(), -1) !== '/') {

                $slashUrl = $this->app->makeUrl('/' . trim($request->getPath(), '/') . '/', $_GET);
                $response->redirect($slashUrl);

                return;
            }
        }

        $controller = $request->getController();

		$this->prepareController($controller, $request, $response);

        $data = $controller->__execute(
            $request->getAction(),
            $request->getActionArgs()
        );

        // For e.g. 301 redirects we don't need to bother rendering
        if (!$response->shouldContinueProcessing()) {
            return;
        }

        if (!is_array($data)) {
            $data = array('data' => $data);
        }

        $renderer = $this->app->createRenderer();

        $renderer->render($controller, $data, $request, $response);
    }

    private function prepareController($controller, $request, $response) {
        $controller->app = $this->app;
        $controller->request = $request;
        $controller->response = $response;
    }

}

?>
