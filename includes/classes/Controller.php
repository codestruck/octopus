<?php

/**
 * Base class for implementing an Octopus controller.
 */
abstract class SG_Controller {

    public $template;
    public $view;
    public $app;
    public $response;

    public function __construct($app, $response) {
        $this->app = $app;
        $this->response = $response;
    }

    /**
     * Redirects the user to a new path.
     */
    protected function redirect($path) {
        $this->response->redirect(u($path));
    }

    /**
     * Redirects the user to the current URL.
     */
    protected function reload() {

    }

    /**
     * If the action specified does not exist on this class, defaultAction()
     * gets called.
     */
    public function defaultAction($action, $args) {}

    /**
     * Called at the end of each action to render the controller data as
     * JSON.
     */
    protected function renderJson($data = array(), $options = null) {

        header('Content-type: application/json');

        echo json_encode($data);

        exit();

    }
}

?>
