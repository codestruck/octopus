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

    private $executedActions = array();

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

        if (strncmp($action, '_', 1) == 0) {
            // Public methods starting with '_' can't be actions.
            return;
        }

        $originalAction = $action;
        if (!$args) $args = array();

        if (!method_exists($this, $action)) {
            $action = $originalAction = camel_case($action);
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

        $this->executedActions[] = array('action' => $originalAction, 'args' => $args);
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
     * @return Array The actions that have been executed on this controller
     * (using __execute), in the order they were called.
     */
    public function &__getExecutedActions() {

        $result = array();
        foreach($this->executedActions as $a) {
            $result[] = $a['action'];
        }
        return $result;

    }

    /**
     * @return bool Whether the given action exists on this controller.
     */
    protected function actionExists($action) {

        $action = trim($action);

        if (strncmp($action, '_', 1) == 0) {
            return false;
        }

        return method_exists($this, camel_case($action));
    }

    /**
     * Redirects the user either to a new path in the app or to a different
     * action on this controller. If $pathOrAction is a valid action on this
     * controller and $isAction is not specified, you'll be redirected to an
     * action.
     *
     * The difference between this and transfer($action) is that transfer will
     * reset the controller before calling the new action. This means that
     * by default you'll get the view for $pathOrAction, whereas with
     * transfer() you get the view of the original controller by default.
     *
     * @param $pathOrAction String Either a path in the app or an action on
     * this controller.
     * @param $isAction Mixed Whether or not $pathOrAction is an action. Can
     * also be an array of arguments to pass to the action being redirected to.
     */
    protected function redirect($pathOrAction, $isAction = null) {

        $args = null;

        if (is_array($isAction)) {
            $args = $isAction;
            $isAction = true;
        }

        if ($isAction === null &&
            strpos($pathOrAction, '/') === false &&
            $this->actionExists($pathOrAction)) {
            $isAction = true;
        }

        if ($isAction) {
            return $this->redirectToAction($pathOrAction, $args);
        }

        // TODO: apply args to path?

        $path = u($pathOrAction);

        if (should_redirect()) {
            $this->response->redirect($path);
        } else {
            notify_of_squashed_redirect($path, $this->response);
        }
    }

    protected function redirectToAction($action /*, $arg1, $arg2, $arg3 */) {

        $args = func_get_args();
        array_shift($args);
        if (count($args) == 1 && is_array($args[0])) {
            $args = $args[0];
        }

        return $this->__execute($action, $args);
    }

    /**
     * Redirects the user to the current URL.
     */
    protected function reload() {
        $this->redirect($_SERVER['REQUEST_URI']);
    }

    /**
     * Calls a different action without doing a 301/302 redirect.
     */
    protected function transfer($action, $args = array()) {

        $this->template = null;
        $this->view = null;
        $this->executedActions = array();

        $args = func_get_args();
        array_shift($args);

        if (count($args) == 1 && is_array($args[0])) {
            $args = $args[0];
        }

        return $this->__execute($action, $args);
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
     * Returns whether a given action is currently being executed, taking into
     * account calls to transfer().
     *
     * @return bool True if $action is currently being executed, false
     * otherwise.
     */
    protected function executingAction($action) {
        $action = camel_case($action);
        foreach($this->executedActions as $a) {
            if ($a['action'] == $action) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return The value of the arg at index $index passed to the currently
     * executing action.
     */
    protected function getArg($index, $default = null) {
        $args = $this->getArgs();
        return isset($args[$index]) ? $args[$index] : $default;
    }

    /**
     * @return Array Args passed to the current action.
     */
    protected function getArgs() {
        $ct = count($this->executedActions);
        if ($ct) {
            return $this->executedActions[$ct - 1]['args'];
        } else {
            return array();
        }
    }

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
