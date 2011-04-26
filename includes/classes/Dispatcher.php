<?php

SG::loadClass('SG_Renderer');

/**
 * Class responsible for locating controllers and rendering views.
 */
class SG_Dispatcher {

    public static $defaultTemplate = 'html/page';

    public static $defaultView = 'default';

    private $_app;

    public function __construct($app = null) {
        $this->_app = ($app ? $app : SG_App::singleton());
    }

    /**
     * Given a path, renders the page and generates an SG_Response
     * instance for it.
     * @param $path String A path in the app.
     * @return Object An SG_Response instance.
     */
    public function &getResponse($path) {

        $originalPath = trim($path);
        $path = trim($path, '/');

        $info = $this->getControllerInfo($path);

        if (!$info) $info = array();

        if (empty($info['controller']) || empty($info['action'])) {

            $parts = explode('/', $path);

            if (empty($info['controller'])) {
                $info['controller'] = array_shift($parts);
            }

            if (empty($info['action'])) {
                $info['action'] = array_shift($parts);
            }

            if (empty($info['args'])) {
                $info['args'] = $parts;
            }
        }

        $response = new SG_Response();

        if (empty($info['action'])) {

            // No action specified == index, but we need to make sure the
            // path ends with a '/'.
            if (substr($originalPath, strlen($originalPath) - 1, 1) != '/') {
                $response->redirect($this->_app->makeUrl('/' . $path . '/'));
                return $response;
            }

        }

        $controller = $this->createController($info, $response);

        if (!$controller) {
            $controller = $this->createDefaultController($info, $response);
        }

        $data = $this->execute(
            $response,
            $controller,
            empty($info['action']) ? 'index' : $info['action'],
            empty($info['args']) ? array() : $info['args']
        );

        // For e.g. 301 redirects we don't need to bother rendering
        if (!$response->shouldContinueProcessing()) {
            return $response;
        }

        $template = $controller->template;
        $view = $controller->view;

        $this->render(
            isset($info['controller']) ? $info['controller'] : '',
            $template, $view, $data, $response
        );

        return $response;
    }

    /**
     * Given the controller info array from an SG_Nav_Item, instantiates the
     * appropriate controller instance.
     * @return Object An SG_Controller if found, otherwise NULL.
     */
    protected function &createController(&$info, $response) {

        $controller = null;

        if (empty($info['controller'])) {
            return $controller;
        }

        $name = preg_replace('/Controller$/', '', $info['controller']);
        $controllerFile = $this->_app->getFile('controllers/' . $name . '.php');

        if (!$controllerFile) {
            return $controller;
        }

        require_once($controllerFile);

        $className = $name . 'Controller';

        if (!class_exists($className)) {
            $response->notFound();
            app_error("Found controller file, but class $className does not exist.");
            $info['action'] = 'error';
        } else {
            $controller = new $className($this->_app, $response);
            $this->configureController($controller, $info);
        }

        return $controller;

    }

    protected function &createDefaultController($info, $response) {
        require_once($this->_app->getOption('OCTOPUS_DIR') . 'controllers/Default.php');

        $controller = new DefaultController($this->_app, $response);
        $controller->requestedController = $info['controller'];
        $this->configureController($controller, $info);

        return $controller;
    }

    private function configureController($controller, $info) {

        $controller->template = (isset($info['template']) ? $info['template'] : self::$defaultTemplate);

        if (isset($info['view'])) {
            $controller->view = $info['view'];
        } else if (isset($info['action'])) {
            $controller->view = $info['action'];
        } else {
            $controller->view = self::$defaultView;
        }

    }

    /**
     * Given a path, figure out what controller it should use, what action
     * should be called, and what args should be passed.
     */
    private function getControllerInfo($path) {

        $controllers = $this->_app->getControllers(true);
        $parts = explode('/', $path);

        // Find the most specific controller we can
        $controller = '';
        $found = false;
        while(($p = array_shift($parts))) {

            if (!$p) {
                continue;
            }

            $controller .= ($controller ? '_' : '') . camel_case($p, true);

            if (in_array($controller, $controllers)) {
                // Found it!
                $found = true;
                break;
            }
        }

        if (!$found) {
            return false;
        }

        $action = array_shift($parts);
        if (!$action) $action = 'index';

        return array(
          'controller' => $controller,
          'action' => $action,
          'args' => $parts ? $parts : array()
        );
    }

    /**
     * Actually executes an action on a controller and returns the results.
     */
    protected function execute($resp, $controller, $action, $args) {

        $originalAction = $action;

        if (!$args) $args = array();

        if (!method_exists($controller, $action)) {

            $action = camel_case($action);
            if (!method_exists($controller, $action)) {
                $action = 'defaultAction';
            }
        }

        if (method_exists($controller, '_before')) {
            $controller->_before($originalAction, $args);
        }

        $beforeMethod = 'before_' . $originalAction;
        if (method_exists($controller, $beforeMethod)) {
            $result = $controller->$beforeMethod($args);
            if ($result === false) {
                // Short-circuit out
                return;
            }
        }

        if ($originalAction != $action) {

            // Support before_defaultAction as well
            $beforeMethod = 'before_' . $action;
            if (method_exists($controller, $beforeMethod)) {
                $result = $controller->$beforeMethod($args);
                if ($result === false) {
                    // Short-circuit
                    return;
                }
            }
        }

        if ($action == 'defaultAction') {
            // Special case-- pass args as an array along w/ action
            $data = $controller->defaultAction($originalAction, $args);
        } else {

            $haveArgs = !!count($args);

            if (!$haveArgs) {

                // Easy enough
                $data = $controller->$action();

            } else {

                /* If args is an associative array, pass in as a single
                 * argument. Otherwise, assume each value in the array maps
                 * to a corresponding argument in the action.
                 */

                if (is_associative_array($args)) {
                    $data = $controller->$action($args);
                } else {
                    $data = call_user_func_array(array($controller, $action), $args);
                }
            }
        }

        // Call the after_ actions

        if ($originalAction != $action) {

            $afterMethod = 'after_' . $action;
            if (method_exists($controller, $afterMethod)) {
                $data = $controller->$afterMethod($args, $data);
            }

        }

        $afterMethod = 'after_'. $originalAction;
        if (method_exists($controller, $afterMethod)) {
            $data = $controller->$afterMethod($args, $data);
        }

        if (method_exists($controller, '_after')) {
            $data = $controller->_after($originalAction, $args, $data);
        }

        return $data;
    }

    /**
     * Renders out the result of an action.
     */
    protected function render($controller, $template, $view, $data, $response) {

        $app = $this->_app;

        $siteDir = $app->getOption('SITE_DIR');
        $octopusDir = $app->getOption('OCTOPUS_DIR');

        $templateFile = $viewFile = false;

        // TODO Theme support

        if (strncmp($template, '/', 1) == 0) {

            // Absolute path
            $templateFile = $template;

        } else if ($template) {
            $templateFile = $app->getFile(
                $template,
                array($siteDir . 'themes/default/templates/', $octopusDir . 'themes/default/templates/'),
                array(
                    'extensions' => array('.php', '.tpl')
                )
            );
        }

        if ($response->isForbidden()) {

            // Short-circuit around
            $view = 'sys/forbidden';

        }

        if (strncmp($view, '/', 1) == 0) {

            // Absolute path to view == use that file
            $viewFile = $view;

        } else if ($view) {

            // First look for a view specific to the controller
            if ($controller) {

                $path = str_replace('_', '/', strtolower($controller));
                $path = trim($path, '/');

                $viewFile = $app->getFile(
                    $view,
                    array($siteDir . 'views/' . $path, $octopusDir . 'views/' . $path),
                    array(
                        'extensions' => array('.php', '.tpl')
                    )
                );

            }

            if (!$viewFile) {
                $viewFile = $app->getFile(
                    $view,
                    array($siteDir . 'views/', $octopusDir . 'views/'),
                    array(
                        'extensions' => array('.php', '.tpl')
                    )
                );
            }
        }

        if (!$viewFile) {
            $viewFile = $app->getFile(
                'sys/view-not-found',
                array($siteDir . 'views/', $octopusDir . 'views/'),
                array(
                    'extensions' => array('.php', '.tpl')
                )
            );
        }

        $viewContent = $templateContent = '';

        if ($viewFile) {
            $viewRenderer = SG_Renderer::createForFile($viewFile);
            $viewContent = $viewRenderer->render($data);
        } else {
            // TODO handle view not found
            return;
            //die("View not found: $view");
        }

        if ($templateFile) {
            $templateRenderer = SG_Renderer::createForFile($templateFile);
            $data['view_content'] = $viewContent;
            $templateContent = $templateRenderer->render($data);
        } else {
            // TODO handle template not found
            return;
            // die("Template not found: $template");
        }



        $response->append($templateContent);

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

}

?>
