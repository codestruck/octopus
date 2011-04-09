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
     * @param $path Mixed A path in the app or an SG_Nav_Item instance.
     * @return Object An SG_Response instance.
     */
    public function &getResponse($path) {

        if ($path instanceof SG_Nav_Item) {
            $navItem = $path;
        } else {
            $navItem = $this->_app->find($path);
        }

        $response = new SG_Response();

        if (!$navItem) {

            // Item not found = 404
            $response->addHeader('Status', '404 Not Found');
            $info = array('controller' => false, 'action' => false, 'args' => array('path' => $path), 'view' => '404');
            $path = trim($path, '/');

        } else {

            // Item found = Yay! Resolve controller / action
            $info = $this->getControllerInfo($navItem);
            $path = $navItem->getFullPath();
        }

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

        $controller = $this->createController($info, $response);

        if (!$controller) {
            $controller = $this->createDefaultController($info, $response);
        }

        $data = $this->execute(
            $controller,
            empty($info['action']) ? '' : $info['action'],
            empty($info['args']) ? array() : $info['args']
        );

        $template = $controller->getTemplate();
        $view = $controller->getView();

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
        $controllerFile = get_file('controllers/' . $name . '.php');

        if (!$controllerFile) {
            return $controller;
        }

        require_once($controllerFile);

        $className = $name . 'Controller';

        if (!class_exists($className)) {
            $response->notFound();
            $response->addError("Found controller file, but class $className does not exist.");
            $info['action'] = 'error';
        } else {
            $controller = new $className($response);
            $this->configureController($controller, $info);
        }

        return $controller;

    }

    protected function &createDefaultController($info, $response) {
        require_once(OCTOPUS_DIR . 'controllers/Default.php');

        $controller = new DefaultController($response);
        $this->configureController($controller, $info);

        return $controller;
    }

    private function configureController($controller, $info) {

        $controller->setTemplate(isset($info['template']) ? $info['template'] : self::$defaultTemplate);

        if (isset($info['view'])) {
            $controller->setView($info['view']);
        } else if (isset($info['action'])) {
            $controller->setView($info['action']);
        } else {
            $controller->setView(self::$defaultView);
        }

    }

    private function getControllerInfo($navItem) {

        $controllers = $this->_app->getControllers(true);

        $parts = explode('/', $navItem->getFullPath());

        // Find the most specific controller we can
        $controller = '';
        while(($p = array_shift($parts))) {

            if (!$p) {
                continue;
            }

            $controller .= ($controller ? '_' : '') . $p;

            if (isset($controllers[$controller])) {
                // Found it!
                break;
            }
        }

        if (!isset($controllers[$controller])) {
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

    protected function execute($controller, $action, $args) {

        $resp = $controller->getResponse();

        if (!$action) $action = 'defaultAction';
        if (!$args) $args = array();

        if (!method_exists($controller, $action)) {

            $action = camel_case($action);
            if (!method_exists($controller, $action)) {

                // TODO Swap over to DefaultController and serve up an
                // error().

                return array('error' => "Action not found: $action");
            }
        }

        if ($action == 'defaultAction' || $action == 'error') {
            // Special case-- pass args
            $ret = $controller->$action($args);
        } else {

            $positionalArgs = array();
            $last = -1;
            foreach($args as $key => $value) {
                if (is_numeric($key) && $key == $last + 1) {
                    $positionalArgs[] = $value;
                    $last++;
                }
            }

            if (empty($positionalArgs)) {
                $ret = $controller->$action($args);
            } else {
                $positionalArgs[] = $args;
                $ret = call_user_func_array(array($controller,$action), $positionalArgs);
            }
        }

        return $ret;
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
        if ($template) {
            $templateFile = $app->getFile(
                $template,
                array($siteDir . 'themes/default/templates/', $octopusDir . 'themes/default/templates/'),
                array(
                    'extensions' => array('.php', '.tpl')
                )
            );
        }

        dump_r($controller, $view);

        if ($view) {

            // First look for a view specific to the controller
            if ($controller) {

                $path = str_replace('_', '/', $controller);
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
