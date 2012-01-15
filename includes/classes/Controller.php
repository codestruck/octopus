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

    /**
     * Set this to any model you want to scaffold index, add, edit, view, and
     * delete actions for.
     */
    protected $scaffold = null;

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

        if (strncmp($action, '_', 1) === 0) {
            // Public methods starting with '_' can't be actions
            return;
        }

        if (!$action) {
            $action = 'index';
        }

        $originalAction = $action;
        if (!$args) $args = array();
        $action = $actionMethod = null;

        $result = array();

        if ($this->resolveAction($originalAction, $action, $actionMethod, $args, $beforeArgs, $afterArgs, $result)) {

	        if ($this->executeBeforeMethods($originalAction, $action, $actionMethod, $beforeArgs, $result)) {

		        $this->executedActions[] = array('action' => $originalAction, 'args' => $args);

	        	$result = $this->executeAction($originalAction, $action, $actionMethod, $args);

	        	$result = $this->executeAfterMethods($originalAction, $action, $actionMethod, $afterArgs, $result);

	        }
	    }

		if (is_array($result)) {
			$result = array_map(array($this, 'escape'), $result);
		}

		return $result;
    }

    /**
     * Executes all the _before methods present for $action.
     * @param String $originalAction The original requested action
     * @param String $action The normalized action name (camel case, any
	 * trailing 'Action' or '_action' removed).
     * @param String $actionMethod The actual method on this class that will
     * be executed for the action.
     * @param Array $args,
 	 * @param Mixed $result If this method returns false, this is set to the
 	 * result to return for the action.
     * @return Boolean True to allow executing the action, false otherwise.
     */
    protected function executeBeforeMethods($originalAction, $action, $actionMethod, Array $args, &$result) {

        $beforeMethods = $this->getBeforeMethods($originalAction, $action, $actionMethod);

        foreach($beforeMethods as $beforeMethod => $actionAsFirstArg) {

            if (!is_callable(array($this, $beforeMethod))) {
                continue;
            }

            if ($actionAsFirstArg) {
                $result = $this->$beforeMethod($action, $args);
            } else {
                $result = $this->$beforeMethod($args);
            }

            if ($this->isFailure($result)) {
                return false;
            }

        }

        return true;

    }

    /**
     * Actually calls the method that corresponds to $action on this controller.
     * @param String $originalAction The original requested action
     * @param String $action The normalized action name (camel case, any
	 * trailing 'Action' or '_action' removed).
     * @param String $actionMethod The actual method on this class that will
     * be executed for the action.
     * @param Array $args Arguments to pass to the action.
     */
    protected function executeAction($originalAction, $action, $actionMethod, Array $args) {

    	if ($actionMethod === '_default') {
    		return $this->_default($action, $args);
    	}

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
     * Executes any _after methods for the given action.
     * @param String $originalAction The original requested action
     * @param String $action The normalized action name (camel case, any
	 * trailing 'Action' or '_action' removed).
     * @param String $actionMethod The actual method on this class that will
     * be executed for the action.
	 * @param Array $args Arguments passed to the action
	 * @param Mixed $result Result of the action.
	 * @return Mixed The final result after running all _after methods.
     */
    protected function executeAfterMethods($originalAction, $action, $actionMethod, Array $args, $result) {

        $afterMethods = $this->getAfterMethods($originalAction, $action, $actionMethod);

        $argsWithAction = $args;
        array_unshift($argsWithAction, $action);

        foreach($afterMethods as $afterMethod => $actionAsFirstArg) {

            if (!is_callable(array($this, $afterMethod))) {
                continue;
            }

            if ($actionAsFirstArg) {
                $result = $this->$afterMethod($action, $args, $result);
            } else {
                $result = $this->$afterMethod($args, $result);
            }
        }

        return $result;
    }

    /**
     * Given an incoming action, figures out what method it actually corresponds
     * to and normalizes arguments as appropriate.
     * @return Boolean True to continue with executing $actionMethod, or
     * false to return $result as the action's result.
     */
    protected function resolveAction($originalAction, &$action, &$actionMethod, &$args, &$beforeArgs, &$afterArgs, &$result) {

    	$this->figureOutAction($originalAction, $action, $actionMethod);

    	$beforeArgs = $afterArgs = $args;

    	return true;
    }

    private function figureOutAction($originalAction, &$action, &$actionMethod) {

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
                        $actionMethod = '_default';
                    }

                }

            }

        }

        $action = preg_replace('/(_action|Action)$/', '', $action);
    }

    /**
     * @return An array of '_after' methods to call for the given action.
     * Keys are method names and values are true to include $action as the
     * first argument passed to the method.
     */
    protected function getAfterMethods($originalAction, $action, $actionMethod) {

        $isDefault = ($actionMethod === '_default');

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
     * Keys are the actual methods and values are true if $action should be
     * passed as the first argument.
     */
    protected function getBeforeMethods($originalAction, $action, $actionMethod) {

        $isDefault = ($actionMethod === '_default');

        $b = array('_before' => true);

        // If no method exists for action, but there is a _before_action method,
        // don't pass the action name as the 1st argument
        $b['_before_' . $originalAction] = false;
        $b['_before_' . $action] = false;

        if ($isDefault) {
            $b['_before_default'] = true;
        } else if ($action !== $actionMethod) {
            $b['_before_' . $actionMethod] = false;
        }

        return $b;
    }

    /**
     * @return Boolean Indicating whether a value returned from a _before()
     * method should be regarded as a failure.
     */
    protected function isFailure($result) {

    	// TODO: move the $result['success'] logic into api controller
        return ($result === false) ||
               (is_array($result) && isset($result['success']) && $result['success'] === false);
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
     * @return String URL to the given action.
     */
    protected function getActionUrl($action) {

    	$action = preg_replace('/_?[aA]ction$/', '', $action);
    	$action = dashed($action);

    	$url = preg_replace('/_?Controller$/', '', get_class($this));
    	$url = dashed($url);
    	$url = '/' . $url . '/' . $action;

    	return u($url);
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
     * Sends the browser a 403 error.
     */
    protected function forbidden($newView = '403') {

    	if ($newView !== null) {
    		$this->view = $newView;
    	}

    	$this->response->forbidden();
    }

    /**
     * Sends the browser a 404 error.
     */
    protected function notFound($newView = '404') {

        if ($newView !== null) {
            $this->view = $newView;
        }

        $this->response->notFound();
    }

    /**
     * If the action specified does not exist on this class, _default()
     * gets called.
     */
	public function _default($action, $args) {

		if ($this->tryScaffoldingAction($action, $args, $result)) {
			return $result;
		}

		return array('controller_links' => $this->getControllerLinks());

    }

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

    /**
     * Method used for a generic "add" action.
     */
    protected function scaffoldAddAction($model, $args) {

    	$this->view = 'scaffold_add';

    	$item = new $model();
    	$form = $this->createScaffoldForm($item, 'add');

    	$index_url = $this->getActionUrl('');

    	set_title('Add ' . humanize($model));

    	if ($form->wasSubmitted() && $form->validate()) {

    		$values = $form->getValues();
    		$item->setData($values);

    		if ($item->save()) {
    			set_flash("Added " . h($item));
    			return $this->redirect($index_url);
    		}

    	}

		$controller_links = $this->getControllerLinks();

    	return compact('model', 'form', 'index_url', 'controller_links');
    }

    /**
     * Method used for a generic "delete" action.
     */
    protected function scaffoldDeleteAction($model, $args) {

		$this->view = 'scaffold_delete';

		$id = array_shift($args);
		$item = call_user_func(array($model, 'get'), $id);

		if (!$item) {
			return $this->notFound();
		}

		set_title("Delete " . humanize($model));

		$index_url = $this->getActionUrl('');

		$form = new Octopus_Html_Form('delete' . $model);
		$form->addClass('scaffold-delete');
		$form->addButton('submit', 'Delete');

		if ($form->wasSubmitted() && $form->validate()) {

			$name = h($item);
			$item->delete();
			set_flash("Deleted $name");

			return $this->redirect($index_url);
		}

    	// Allow, e.g. a Product model to be referred to as $product
    	$var = underscore($model);
    	if (!isset($$var)) $$var = $item;

		$controller_links = $this->getControllerLinks();

    	return compact('item', $var, 'model', 'form', 'index_url', 'controller_links');
    }


    /**
     * Method used for a generic "edit" action.
     */
    protected function scaffoldEditAction($model, $args) {

		$this->view = 'scaffold_edit';

    	$id = array_shift($args);
    	$item = call_user_func(array($model, 'get'), $id);

    	if (!$item) {
    		return $this->notFound('404');
    	}

    	set_title("Edit " . humanize($model));

    	$index_url = $this->getActionUrl('');

		$form = $this->createScaffoldForm($item, 'edit');

		if ($form->wasSubmitted()) {

			if ($form->validate()) {

				$values = $form->getValues();
				$item->setData($values);

				if ($item->save()) {
					set_flash('Saved changes to ' . h($item));
					return $this->redirect($index_url);
				}

			}
		} else {
			$form->setValues($item);
		}

		// Allow, e.g. a Product model to be referred to as $product
    	$var = underscore($model);
    	if (!isset($$var)) $$var = $item;

		$controller_links = $this->getControllerLinks();

		return compact('id', 'model', 'item', $var, 'form', 'index_url', 'controller_links');
    }

    /**
     * Method used for a generic "index" (list) action.
     */
    protected function scaffoldIndexAction($model, $args) {

		$this->view = 'scaffold_index';

    	if (!empty($args)) {
    		return $this->scaffoldEdit($model, $args);
    	}

    	set_title(pluralize(humanize($model)));

    	$table = $this->createScaffoldTable($model);

    	$add_url = $this->getActionUrl('add');

		$controller_links = $this->getControllerLinks();

    	return compact('model', 'table', 'add_url', 'controller_links');
    }

    /**
     * Method used for a generic "view" action.
     */
    protected function scaffoldViewAction($model, $args) {

		$this->view = 'scaffold_view';

    	$id = array_shift($args);
    	$item = call_user_func(array($model, 'get'), $id);

    	if (!$item) {
    		return $this->notFound();
    	}

    	$index_url = $this->getActionUrl('');

    	$fields = array();
    	foreach($item->getFields() as $f) {
    		$fields[] = $f->getFieldName();
    	}

    	// Allow, e.g. a Product model to be referred to as $product
    	$var = underscore($model);
    	if (!isset($$var)) $$var = $item;

    	$controller_links = $this->getControllerLinks();

    	return compact('id', 'item', $var, 'model', 'fields', 'index_url', 'controller_links');

    }

    private function escape($value) {
        if ($value instanceof Octopus_Model) {
            $value->escape();
        }

        return $value;
    }

    private function createScaffoldForm($item, $action) {

		$form = new Octopus_Html_Form($action . get_class($item));

		$form->addClass('scaffold-' . $action);

    	foreach($item->getFields() as $field) {
    		$field->addToForm($form);
    	}

    	$form->addButton('submit', 'Save');

    	$fields = $form->getFields();
    	$first = array_shift($fields);
    	if ($first) {
    		$first->autoFocus();
    	}

    	return $form;

    }

    private function createScaffoldTable($model) {

    	$table = new Octopus_Html_Table(camel_case($model) . 'Table');

    	$table->addClass('scaffold-list');

    	$item = new $model();
    	foreach($item->getFields() as $field) {
    		$field->addToTable($table);
    	}

    	$table->addColumns(array(
    		'actions' => array(
    			'view' => array('url' => 'view/{$id}'),
    			'edit' => array('url' => 'edit/{$id}'),
    			'delete' => array('url' => 'delete/{$id}')
	    	)
	    ));

    	$table->setDataSource(call_user_func(array($model, 'all')));

    	return $table;

    }

    private function tryScaffoldingAction($action, $args, &$result) {

		if (!$this->scaffold) {
			return false;
		}

		$scaffoldMethod = 'scaffold' . camel_case($action, true) . 'Action';

		if (method_exists($this, $scaffoldMethod)) {
			$result = $this->$scaffoldMethod($this->scaffold, $args);
			return true;
		}

		return false;
    }

    /**
     * @return Array Keys are text, values are urls. Links to the controllers
     * in the site's controllers/ directory. This is used by the default theme
     * to render a nav.
     */
    private function getControllerLinks() {

    	$links = array();

    	$files = glob(get_option('SITE_DIR') . 'controllers/*.php');
    	if (!$files) return $links;

    	foreach($files as $f) {

    		$name = basename($f, '.php');
    		$url = '/' . dashed($name);
    		$name = humanize($name);

    		$links[$name] = u($url);

    	}

    	return $links;

    }

}

?>
