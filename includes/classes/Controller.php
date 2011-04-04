<?php

/**
 * Base class for implementing an Octopus controller.
 */
abstract class SG_Controller {

    protected $_response;
    protected $_calledAction;

    /**
     * Creates a new SG_Controller instance for the given SG_Response.
     */
    public function __construct($response = null) {
        $this->setResponse($response);
    }

    public function &getResponse() {
        return $this->_response;
    }

    public function &setResponse($resp) {
        $this->_response = $resp;
        return $this;
    }

    /**
     * @return String The original action that was requested.
     */
    public function getCalledAction() {
        return $this->_calledAction;
    }

    public function setCalledAction($action) {
        $this->_calledAction = $action;
    }

    /**
     * If the action specified does not exist on this class, defaultAction()
     * gets called.
     */
    public function defaultAction($args) {}

    public function error() {
    }

    /**
     * @return String View html for the 404 page.
     */
    public function notFound($args) {
        $this->getResponse()->addHeader('Status', '404 Not Found');
        return $this->render('404', $args);
    }

    /**
     * Called at the end of each action to render the view HTML.
     * @return String View html.
     */
    public function render($view = null, $data = null) {

        if ($view && is_array($view)) {
            $data = $view;
            $view = false;
        }

        // Try and find a sensible default view
        $view = self::findView(get_class($this), $view ? $view : $this->getCalledAction());
        $response = $this->getResponse();

        // TODO real theme support

        $template = get_file(
            'templates/html/page',
            array(
                SITE_DIR . 'themes/default',
                OCTOPUS_DIR . 'themes/default'
            ),
            array(
                'extensions' => array('.php', '.tpl'),
            )
        );

        // TODO: Smarty rendering

        if ($view && file_exists($view)) {
            ob_start();
            include($view);
            $content = ob_get_clean();

            if ($template) {
                ob_start();
                include($template);
                $response->append(ob_get_clean());
            } else {
                $response->append($content);
            }

        } else {
            $response->append('<p>View Not Found: ' . htmlspecialchars($view) . '</p>');
        }

    }

    public static function findView($controllerClass, $actionOrView, $options = array()) {

        $controllerClass = preg_replace('/Controller$/', '', $controllerClass);

        if (empty($options['extensions'])) $options['extensions'] = array('.php', '.tpl');

        $view = get_file(
            array(
                "views/$controllerClass/$actionOrView",
                "views/$actionOrView"
            ),
            null,
            $options
        );
        if ($view) return $view;

        return get_file('views/default', null, array('extensions' => array('.php','.tpl')));
    }

    /**
     * Called at the end of each action to render the controller data as
     * JSON.
     */
    public function renderJson($data = array(), $options = null) {

        header('Content-type: application/json');

        echo json_encode($data);

        exit();

    }

    public static function execute($controller, $action, $response) {

        $controller->response = $respons;

    }

}

?>
