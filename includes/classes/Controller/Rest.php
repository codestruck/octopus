<?php

/**
 * Base for implementing REST controllers.
 */
abstract class Octopus_Controller_Rest extends Octopus_Controller {

    public function setResponseContentType() {
        $this->response->contentType('application/json');
    }

    public function __execute($action, $args) {

        $this->setResponseContentType();

        $result = parent::__execute($action, $args);

        $this->response->append(json_encode($result));
        $this->response->stop();

        return $result;
    }

    public function success($data) {
        return $data;
    }

    public function error($errors, $status = 403) {

        $result = array(
            'errors' => $errors,
        );

        $this->response->setStatus($status);

        return $result;
    }

    public function _methodNotFound() {

        $allowedMethods = array('post', 'get', 'put', 'delete');
        if (in_array($this->request->getMethod(), $allowedMethods)) {
            return $this->error(array('Method not implemented'), 405);
        } else {
            return $this->error(array('Method not recognised'), 501);
        }
    }

    protected function shouldExecute($action, $args) {
        return true;
    }

    protected function resolveAction($originalAction, &$action, &$actionMethod, &$args, &$beforeArgs, &$afterArgs, &$result) {

        if (!$this->findAction($originalAction, $action, $actionMethod)) {
            $action = $actionMethod = '_methodNotFound';
        }

        $class = new ReflectionClass($this);
        $method = null;

        $method = $class->getMethod($actionMethod);

        $errors = array();
        $positionalArgs = array();

        $httpMethod = $this->request->getMethod();
        switch ($httpMethod) {
            case 'get':
            case 'delete':
                foreach($method->getParameters() as $param) {

                    $pos = $param->getPosition();
                    $name = $param->getName();
                    $required = !$param->isDefaultValueAvailable();
                    $default = $required ? null : $param->getDefaultValue();

                    $exists = array_key_exists($pos, $args);

                    if ($required && !$exists) {
                        $errors[$name] = "$name is required.";
                        continue;
                    }

                    $positionalArgs[$pos] = $exists ? $args[$pos] : $default;
                    unset($args[$name]);
                }
                break;

            case 'put':
            case 'post':
                $args = array_merge($args, $this->request->getInputData());
                foreach($method->getParameters() as $param) {

                    $pos = $param->getPosition();
                    $name = $param->getName();
                    $required = !$param->isDefaultValueAvailable();
                    $default = $required ? null : $param->getDefaultValue();
                    $item = $default;

                    $exists = array_key_exists($name, $args);
                    if ($exists) {
                        $key = $name;
                        $item = $args[$name];
                    } else {
                        $exists = array_key_exists($pos, $args);
                        if ($exists) {
                            $key = $pos;
                            $item = $args[$pos];
                        }
                    }

                    if ($required && !$exists) {
                        $errors[$name] = "$name is required.";
                        continue;
                    }

                    $positionalArgs[$key] = $item;
                    unset($args[$name]);
                }
                break;
        }

        if (count($errors)) {
            $result = $this->error($errors, 400);
            return false;
        }

        $beforeArgs = $afterArgs = $args = $positionalArgs;
        return true;
    }

    private function findAction($originalAction, &$action, &$actionMethod) {

        $actionMethod = $this->request->getMethod() . 'Action';
        if (is_callable_and_public($this, $actionMethod)) {
            return true;
        }

        return false;

    }

}
