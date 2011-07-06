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

        $action = trim($action);

        if (strncmp($action, '_', 1) == 0) {
            // Public methods starting with '_' can't be actions
            return;
        }

        $originalAction = $action;
        if (!$args) $args = array();
        $action = $actionMethod = null;

        $this->figureOutActions($originalAction, $action, $actionMethod);

        $beforeMethods = $this->getBeforeMethods($originalAction, $action, $actionMethod);

        foreach($beforeMethods as $beforeMethod => $includeActionInArgs) {

            if (!is_callable_and_public($this, $beforeMethod)) {
                continue;
            }

            if ($includeActionInArgs) {
                $result = $this->$beforeMethod($originalAction, $args);
            } else {
                $result = $this->$beforeMethod($args);
            }

            if ($this->isFailure($result)) {
                return $result;
            }

        }

        $originalArgs = $args;

        if ($action === '_default') {
            $args = array($originalAction, $args);
        }

        $this->executedActions[] = array('action' => $originalAction, 'args' => $args);
        $data = $this->__executeAction($action, $actionMethod, $args);

        $afterMethods = $this->getAfterMethods($originalAction, $action, $actionMethod);

        foreach($afterMethods as $afterMethod => $includeAction) {

            if (!is_callable_and_public($this, $afterMethod)) {
                continue;
            }

            if ($includeAction) {
                $data = $this->$afterMethod($originalAction, $originalArgs, $data);
            } else {
                $data = $this->$afterMethod($originalArgs, $data);
            }
        }

        if (is_array($data)) {
            $data = array_map(array($this, 'escape'), $data);
        }

        return $data;
    }

    /**
     * Given an incoming action name, resolves the 'true' name of the action
     * as well as the method to use to call it.
     */
    private function figureOutActions($originalAction, &$action, &$actionMethod) {

        /*
         * Possible methods for an action called 'foo-bar':
         *
         *  fooBar
         *  fooBarAction
         *  foo_bar
         *  foo_bar_action
         */

        $action = $actionMethod = camel_case($originalAction);

        if (!is_callable_and_public($this, $actionMethod)) {

            $actionMethod .= 'Action';

            if (!is_callable_and_public($this, $actionMethod)) {

                $action = $actionMethod = underscore($action);

                if (!is_callable_and_public($this, $actionMethod)) {

                    $actionMethod .= '_action';

                    if (!is_callable_and_public($this, $actionMethod)) {
                        $action = $actionMethod = '_default';
                    }

                }

            }

        }

        $action = preg_replace('/(_action|Action)$/', '', $action);
    }

    /**
     * @return An array of '_after' methods to call for the given action.
     */
    private function &getAfterMethods($originalAction, $action, $actionMethod) {

        $isDefault = ($action === '_default');

        $a = array();

        if ($isDefault) {
            $a['_after_default'] = true;
        } else if ($action !== $actionMethod) {
            $a['_after_' . $actionMethod] = false;
        }

        $a['_after_' . $action] = $isDefault;
        $a['_after_' . $originalAction] = false;

        $a['_after'] = true;

        return $a;
    }

    /**
     * @return An array of '_before' methods to call for the given action.
     */
    private function &getBeforeMethods($originalAction, $action, $actionMethod) {

        $isDefault = ($action === '_default');

        $b = array('_before' => true);

        $b['_before_' . $originalAction] = false;
        $b['_before_' . $action] = $isDefault;

        if ($isDefault) {
            $b['_before_default'] = true;
        } else if ($action !== $actionMethod) {
            $b['_before_' . $actionMethod] = false;
        }

        return $b;
    }

    private function isFailure($result) {

        return ($result === false) ||
               (is_array($result) && isset($result['success']) && $result['success'] === false);
    }

    /**
     * Actually calls the method that corresponds to $action on this controller.
     * @param string $action Name of action, e.g. 'fooBar',
     * @param string $actionMethod Name of the method, e.g. 'fooBarAction'
     * @param Array $args Arguments to pass to the action.
     */
    protected function __executeAction($action, $actionMethod, $args) {

        $haveArgs = !!count($args);

        if (!$haveArgs) {

            // Easy enough
            return $this->$actionMethod();

        } else {

            /* If args is an associative array, pass in as a single
             * argument. Otherwise, assume each value in the array maps
             * to a corresponding argument in the action.
             */

            if (is_associative_array($args)) {
                return $this->$actionMethod($args);
            } else {
                return call_user_func_array(array($this, $actionMethod), $args);
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

        $camelAction = camel_case($action);
        if (is_callable_and_public($this, $camelAction)) {
            return true;
        }

        if (is_callable_and_public($this, $camelAction . 'Action')) {
            return true;
        }

        $underscoreAction = underscore($camelAction);
        if (is_callable_and_public($this, $underscoreAction)) {
            return true;
        }

        if (is_callable_and_public($this, $underscoreAction . '_action')) {
            return true;
        }

        return false;
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
     * If the action specified does not exist on this class, _default()
     * gets called.
     */
    public function _default($action, $args) {}

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

        $dumped_content = get_dumped_content();
        $data = array_merge($data, $dumped_content);
        output_dumped_content_header(array_pop($dumped_content), $this->response);

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

        $dumped_content = get_dumped_content();
        $data = array_merge($data, $dumped_content);
        output_dumped_content_header(array_pop($dumped_content), $this->response);

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
