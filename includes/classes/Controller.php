<?php

/**
 * Base class for implementing an Octopus controller.
 */
abstract class Octopus_Controller {

    public $template;
    public $view;
    public $app;
    public $request;
    public $response;

    public function __construct($app = null, $request = null, $response = null) {
        $this->app = $app;
        $this->request = $request;
        $this->response = $response;
    }

    /**
     * Executes an action on this controller.
     * @return Mixed The result of the action.
     */
    public function __execute($action, $args) {

        $originalAction = $action;
        if (!$args) $args = array();

        if (!method_exists($this, $action)) {
            $action = camel_case($action);
            if (!method_exists($this, $action)) {
                $action = 'defaultAction';
            }
        }

        // Execute the global _before action
        if (method_exists($this, '_before')) {
            $result = $this->_before($originalAction, $args);
            if ($this->isFailure($result)) {
                return $result;
            }
        }

        // Execute the action-specific _before
        $beforeMethod = 'before_' . $originalAction;
        if (method_exists($this, $beforeMethod)) {
            $result = $this->$beforeMethod($args);
            if ($this->isFailure($result)) {
                return $result;
            }
        }

        if ($originalAction != $action) {
            // Support before_defaultAction as well
            $beforeMethod = 'before_' . $action;
            if (method_exists($this, $beforeMethod)) {
                $result = $this->$beforeMethod($originalAction, $args);
                if ($this->isFailure($result)) {
                    // Short-circuit
                    return $result;
                }
            }
        }

        $originalArgs = $args;

        if ($action == 'defaultAction') {
            $args = array($originalAction, $args);
        }

        $data = $this->__executeAction($action, $args);

        // Support after_defaultAction
        if ($originalAction != $action) {
            $afterMethod = 'after_' . $action;
            if (method_exists($this, $afterMethod)) {
                $data = $this->$afterMethod($originalAction, $originalArgs, $data);
            }
        }

        $afterMethod = 'after_'. $originalAction;
        if (method_exists($this, $afterMethod)) {
            $data = $this->$afterMethod($originalArgs, $data);
        }

        if (method_exists($this, '_after')) {
            $data = $this->_after($originalAction, $originalArgs, $data);
        }

        if (is_array($data)) {
            $data = array_map(array($this, 'escape'), $data);
        }

        return $data;
    }

    private function isFailure($result) {

        return ($result === false) ||
               (is_array($result) && isset($result['success']) && $result['success'] === false);
    }

    protected function __executeAction($action, $args) {

        $haveArgs = !!count($args);

        if (!$haveArgs) {

            // Easy enough
            return $this->$action();

        } else {

            /* If args is an associative array, pass in as a single
             * argument. Otherwise, assume each value in the array maps
             * to a corresponding argument in the action.
             */

            if (is_associative_array($args)) {
                return $this->$action($args);
            } else {
                return call_user_func_array(array($this, $action), $args);
            }
        }
    }

    /**
     * Redirects the user to a new path.
     */
    protected function redirect($path) {

        $path = u($path);

        if (should_redirect()) {
            $this->response->redirect($path);
        } else {
            notify_of_squashed_redirect($path, $this->response);
        }
    }

    /**
     * Redirects the user to the current URL.
     */
    protected function reload() {
        $this->redirect($_SERVER['REQUEST_URI']);
    }

    /**
     * Sends the browser a 404 error.
     */
    protected function notFound($newView = null) {

        if ($newView !== null) {
            $this->view = $newView;
        }

        $this->response->notFound();
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

        $this->response
            ->contentType('application/json')
            ->append(json_encode($data))
            ->stop();

    }

    protected function renderJsonp($data = array(), $function = null, $options = null) {

        if ($function === null) {
            // jQuery specifies the name of the callback function via the
            // 'callback' argument.
            $function = $_GET['callback'];
        }

        $this->response
            ->contentType('application/javascript')
            ->append($function . '(' . json_encode($data) . ');')
            ->stop();

    }

    private function escape($value) {
        if ($value instanceof Octopus_Model) {
            $value->escape();
        }

        return $value;
    }

}

?>
