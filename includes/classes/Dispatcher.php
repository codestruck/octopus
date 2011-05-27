<?php

Octopus::loadClass('Octopus_Renderer');

/**
 * Class responsible for locating controllers and rendering views.
 */
class Octopus_Dispatcher {

    public static $defaultTemplate = 'html/page';

    public static $defaultView = 'default';

    private $_app;

    public function __construct($app = null) {
        $this->_app = ($app ? $app : Octopus_App::singleton());
    }

    /**
     * Given an Octopus_Request, renders the page and generates an
     * Octopus_Response instance for it.
     * @param $request Object An Octopus_Request instance.
     * @return Object An Octopus_Response instance.
     */
    public function &getResponse($request, $buffer = false) {

        $path = $request->getResolvedPath();
        $originalPath = $request->getPath();

        $pathParts = array_filter(explode('/', $path), 'trim');

        $info = $this->getControllerInfo($pathParts);

        if (empty($info['controller']) || empty($info['action'])) {

            if (empty($info['controller']) && $pathParts) {
                $info['controller'] = array_shift($pathParts);
            }

            if (empty($info['action']) && $pathParts) {
                $info['action'] = array_shift($pathParts);
            }

            if (empty($info['args']) && $pathParts) {
                $info['args'] = $pathParts;
            }
        }

        $response = new Octopus_Response($buffer);

        if (empty($info['action'])) {

            // No action specified == index, but we need to make sure the
            // path ends with a '/'.
            if (substr($originalPath, -1) != '/') {
                $response->redirect($this->_app->makeUrl('/' . trim($path, '/') . '/'));
                return $response;
            }

        }

        $controller = $this->createController($info, $response);

        if (!$controller) {
            $controller = $this->createDefaultController($info, $response);
        }

        $data = $controller->__execute(
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
     * Given the controller info array from an Octopus_Nav_Item, instantiates the
     * appropriate controller instance.
     * @return Object An Octopus_Controller if found, otherwise NULL.
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
            $controller = new $className();
            $controller->app = $this->_app;
            $controller->response = $response;
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
    private function getControllerInfo($pathParts) {

        $controllers = $this->_app->getControllers(true);

        // Find the most specific controller we can
        $controller = '';
        $found = false;
        while(($p = array_shift($pathParts))) {

            $controller .= ($controller ? '_' : '') . camel_case($p, true);

            if (in_array($controller, $controllers)) {
                // Found it!
                $found = true;
                break;
            }
        }

        if (!$found) {
            return array(
                'controller' => '',
                'action' => '',
                'args' => array()
            );
        }

        $action = array_shift($pathParts);

        return array(
          'controller' => $controller,
          'action' => $action ? $action : 'index',
          'args' => $pathParts ? $pathParts : array()
        );
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
            $viewRenderer = Octopus_Renderer::createForFile($viewFile);
            $viewContent = $viewRenderer->render($data);
        } else {
            // TODO handle view not found
            return;
            //die("View not found: $view");
        }

        if ($templateFile) {
            $templateRenderer = Octopus_Renderer::createForFile($templateFile);
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
