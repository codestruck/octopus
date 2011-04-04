<?php

/**
 * Class responsible for locating controllers and rendering views.
 */
class SG_Dispatcher {


    public function __construct() {
    }



    /**
     * Given a path, renders the page and generates an SG_Response
     * instance for it.
     * @param $path String A path in the app.
     * @return Object An SG_Response instance.
     */
    public function &getResponse($path) {

        global $NAV;
        $nav = empty($this->nav) ? $NAV : $this->nav;

        $navItem = $nav->find($path);

        $response = new SG_Response();

        if ($navItem) {
            $info = $navItem->getControllerInfo();
        } else {
            $info = array('controller' => false, 'action' => 'notFound', 'args' => array('path' => $path));
        }

        $controller = $this->createController($info, $response);

        if (!$controller) {
            $controller = $this->createDefaultController($response);
        }

        $controller->setResponse($response);
        $controller->setCalledAction($info['action']);
        $ret = $this->execute($controller, $info);

        if ($ret && !is_string($ret)) {
            $ret = $controller->render($info['action'], $ret);
        }

        $response->append($ret);

        return $response;
    }

    protected function execute($controller, $info) {

        $resp = $controller->getResponse();

        $action = isset($info['action']) ? $info['action'] : '';
        if (!$action) $action = 'defaultAction';

        $args = isset($info['args']) ? $info['args'] : false;
        if (!$args) $args = array();

        if (!method_exists($controller, $action)) {

            /*
            $action = camel_case($action);
            if (!method_exists($controller, $action)) {
                // No good
                $action = 'error';
            }
            */
            $action = 'error';
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

    protected function &createDefaultController() {
        require_once(OCTOPUS_DIR . 'controllers/Default.php');
        $controller = new DefaultController();
        return $controller;
    }

    protected function &createController($info, $response) {

        $controller = false;

        if (empty($info['controller'])) {
            return $controller;
        }

        $controllerFile = get_file('controllers/' . $info['controller'] . '.php');
        if (!$controllerFile) {
            $response->addError('Controller file not found', $info['controller']);
        } else {

            require_once($controllerFile);

            $className = $info['controller'] . 'Controller';
            if (!class_exists($className)) {
                $response->addError("Found controller file, but class $className does not exist.");
            } else {
                $controller = new $className($response);
            }
        }

        return $controller;
    }

}

?>
