<?php

/**
 * Base class for implementing an Octopus controller.
 */
abstract class SG_Controller {

    private $_app;
    private $_response;
    private $_view;
    private $_template;

    /**
     * Creates a new SG_Controller instance for the given SG_Response.
     */
    public function __construct($app, $response) {
        $this->_app = $app;
        $this->_response = $response;
    }

    /**
     * @return Object The SG_App instance that owns this controller.
     */
    public function getApp() {
        return $this->_app;
    }

    public function &getResponse() {
        return $this->_response;
    }

    /**
     * @return String The template inside which output from this controller
     * will be rendered.
     */
    public function getTemplate() {
        return $this->_template;
    }

    public function setTemplate($template) {
        $this->_template = $template;
    }

    public function getView() {
        return $this->_view;
    }

    public function setView($view) {
        $this->_view = $view;
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
    public function renderJson($data = array(), $options = null) {

        header('Content-type: application/json');

        echo json_encode($data);

        exit();

    }


}

?>
