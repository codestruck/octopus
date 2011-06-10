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

        $response = new Octopus_Response($buffer);

        if (!$request->getRequestedAction()) {

            // No action specified == index, but we need to make sure the
            // path ends with a '/'.
            if (substr($request->getPath(), -1) != '/') {
                $response->redirect($this->_app->makeUrl('/' . trim($request->getPath(), '/') . '/'));
                return $response;
            }
        }

        $controller = $this->createController($request, $response);

        if (!$controller) {
            $controller = $this->createDefaultController($request, $response);
        }

        $data = $controller->__execute(
            $request->getAction(),
            $request->getActionArgs()
        );

        // For e.g. 301 redirects we don't need to bother rendering
        if (!$response->shouldContinueProcessing()) {
            return $response;
        }

        $this->render($controller, $data, $request, $response);

        return $response;
    }

    /**
     * Given the controller info array from an Octopus_Nav_Item, instantiates the
     * appropriate controller instance.
     * @return Object An Octopus_Controller if found, otherwise NULL.
     */
    protected function &createController($request, $response) {

        $class = $request->getControllerClass();

        $controller = null;

        if (!$class) {
            // Requested class does not exist
            $response->notFound();
            app_error("Controller class does not exist: " . $class);
            return false;
        }

        $controller = new $class();
        $this->configureController($controller, $request, $response);

        return $controller;

    }

    private function requireOnce($file) {
        require_once($file);
    }

    protected function &createDefaultController($request, $response) {

        $this->requireOnce($this->_app->getOption('OCTOPUS_DIR') . 'controllers/Default.php');

        $controller = new DefaultController();
        $this->configureController($controller, $request, $response);

        return $controller;
    }

    private function configureController($controller, $request, $response) {

        $controller->app = $this->_app;
        $controller->request = $request;
        $controller->response = $response;

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
     * Renders out the result of an action.
     * @param $controller An Octopus_Controller instance.
     * @param $data Array The result of executing the action on the controller.
     * @param $request Octopus_Request
     * @param $response Octopus_Response
     */
    protected function render($controller, $data, $request, $response) {

        $templateFile = $this->findTemplateForRender($controller, $request);
        $viewFile = $this->findViewForRender($controller, $request, $response);

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

    private function findViewForRender($controller, $request, $response) {

        $view = empty($controller->view) ? '' : $controller->view;

        if ($response->isForbidden()) {

            // Short-circuit around
            $view = 'sys/forbidden';

        }

        $view = trim($view);
        if (!$view) return false; // TODO: use a default view?

        if (strncmp($view, '/', 1) != 0) {

            return self::findView(
                $request->getControllerFile(),
                $request->getControllerClass(),
                $request->getAction(),
                $this->_app
            );

        }

        return $app->getFile(
            $view,
            array($siteDir . 'views/', $octopusDir . 'views/'),
            array(
                'extensions' => array('.php', '.tpl')
            )
        );
    }

    private function findTemplateForRender($controller, $request) {

        $siteDir = $this->_app->getOption('SITE_DIR');
        $octopusDir = $this->_app->getOption('OCTOPUS_DIR');

        $extensions = array('', '.php', '.tpl');
        $theme = $this->_app->getTheme($request);

        $template = $controller->template;

        if (strncmp($template, '/', 1) != 0) {
            $template = $this->_app->getFile(
                $template,
                array($siteDir . 'themes/' . $theme . '/templates/', $octopusDir . 'themes/' . $theme . '/templates/'),
                array(
                    'extensions' => $extensions
                )
            );
        }

        if (!$template) return $template;

        foreach($extensions as $ext) {
            $f = $template . $ext;
            if (is_file($f)) {
                return $f;
            }
        }

        return $template;
    }

    /**
     * Given a path, figures out the controller to use.
     * @return Mixed An array with the following keys:
     * <dl>
     *  <dt>file</dt>
     *  <dd>The PHP file the controller is in.</dd>
     *  <dt>potential_names</dt>
     *  <dd>An array of potential class names for the controller.</dd>
     *  <dt>action</dt>
     *  <dd>The action to be executed on the controller</dd>
     *  <dt>original_action</dt>
     *  <dd>The actual action contained in the path (if '', 'index' will be
     *  substituted in the 'action' key</dd>
     *  <dt>args</dt>
     *  <dd>Arguments to be passed to the action</dd>
     * </dl>
     *
     * If no candidate controllers are found, returns false.
     */
    public static function findController($path, $app) {

        $rawPath = is_array($path) ? $path : explode('/', $path);
        $path = array();

        foreach($rawPath as $p) {
            $p = trim($p);
            if (!$p) continue;
            if ($p == '.') continue;
            if ($p == '..') {
                if ($path) array_pop($path);
                continue;
            }
            $path[] = $p;
        }

        $directoriesToSearch = array(
            $app->getOption('SITE_DIR') . 'controllers/',
            $app->getOption('OCTOPUS_DIR') . 'controllers/'
        );

        $file = null;
        $potential_names = null;
        $action = null;
        $args = null;
        $found = false;

        foreach($directoriesToSearch as $dir) {

            if (self::searchForController($dir, array('/','_'), $path, $file, $potential_names, $action, $args, $original_action)) {
                $found = true;
                break;
            }

        }

        if (!$found) {
            if (is_array($path)) $path = implode('/', $path);
            return false;
        }

        return compact('file', 'potential_names', 'action', 'original_action', 'args');

    }

    private static function safeRequireOnce($file) {
        require_once($file);
    }

    private static function searchForController($dir, $sep, &$pathParts, &$file, &$potentialNames, &$action, &$args, &$originalAction) {

        $potentialNames = array();
        $args = array();
        $action = null;

        /* Ways controller files can be named:
         *  my_controller.php
         *  MyController.php
         *  mycontroller.php
         */

        $parts = $pathParts;

        while(!empty($parts)) {

            $usParts = array_map('underscore', $parts);

            $camelParts = array();
            foreach($usParts as $p) {
                $camelParts[] = camel_case($p, true);
            }

            $lParts = array_map('strtolower', $camelParts);

            $toTry = array();
            foreach($sep as $s) {

                $toTry[implode($s, $usParts)] = true;
                $toTry[implode($s, $camelParts)] = true;
                $toTry[implode($s, $lParts)] = true;
            }

            foreach($toTry as $name => $unused) {

                $file = $dir . $name . '.php';
                $file = get_true_filename($file);

                if ($file) {

                    // Found it!
                    $fullName = empty($parts) ? $name : implode(' ', $parts);
                    $fullName = preg_replace('#[\s-_/]+#', ' ', $fullName);
                    $fullName = ucwords($fullName);

                    $potentialNames[str_replace(' ', '_', $fullName) . 'Controller'] = true;
                    $potentialNames[str_replace(' ', '', $fullName) . 'Controller'] = true;
                    $potentialNames[camel_case(array_pop($usParts), true) . 'Controller'] = true;

                    $potentialNames = array_keys($potentialNames);


                    $originalAction = $action ? $action : '';
                    if (!$action) {
                        $action = 'index';
                    }

                    return true;
                }

            }

            $newAction = array_pop($parts);
            if ($action !== null) {
                array_unshift($args, $action);
            }
            $action = $newAction;
        }

        return false;

    }

    /**
     * @return Mixed The full path to a view file, or false if none could be
     * found.
     * @param $controllerFile string Full path to the controller file.
     * @param $controllerClass string Class of controller being used.
     */
    public static function findView($controllerFile, $controllerClass, $action, $app, $options = array()) {

        $octopusDir = $app->getOption('OCTOPUS_DIR');
        $siteDir = $app->getOption('SITE_DIR');

        $r = null;

        if ($controllerFile && starts_with($controllerFile, $siteDir . 'controllers/', false, $r)) {
            $controller = preg_replace('/\.php$/i', '', $r);
        } else if ($controllerFile && starts_with($controllerFile, $octopusDir . 'controllers/', false, $r)) {
            $controller = preg_replace('/\.php$/i', '', $r);
        } else {
            $controller = preg_replace('/(.*)(Controller)?$/', '$1', $controllerClass);
        }

        $viewDirs = array(
            $app->getOption('SITE_DIR') . 'views/',
            $app->getOption('OCTOPUS_DIR') . 'views/',
        );

        $controller = underscore($controller);
        $parts = explode('/', $controller);

        if (empty($options['extensions'])) {
            $options['extensions'] = array('.php', '.tpl');
        }

        foreach($viewDirs as $dir) {

            $p = $parts;

            $count = count($p);
            if ($action) $count++;


            while($count) {

                $path = $p ? implode('/', $p) : '';

                if ($action) {
                    $path .= ($path ? '/' : '') . $action;
                }

                foreach($options['extensions'] as $ext) {

                    $file = $dir . $path . $ext;
                    $file = get_true_filename($file);

                    if ($file) {
                        return $file;
                    }
                }

                $path = str_replace('_', '/', $path);

                foreach($options['extensions'] as $ext) {

                    $file = $dir . $path . $ext;
                    $file = get_true_filename($file);

                    if ($file) {
                        return $file;
                    }
                }

                array_pop($p);
                $count--;
            }

        }


        if (empty($options['extensions'])) {
            $options['extensions'] = array('.php', '.tpl');
        }


        if (!empty($options['failIfNotFound'])) {
            return false;
        }

        // By default, return "VIEW NOT FOUND" view.
        return $viewDirs[1] . 'sys/not_found.php';
    }

}

?>
