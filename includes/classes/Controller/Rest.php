<?php

/**
 * Base for implementing REST controllers.
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
abstract class Octopus_Controller_Rest extends Octopus_Controller {

    public $resource_id = null;
    private $isError = false;

    public function setResponseContentType() {
        $this->response->contentType = 'application/json';
    }

    public function __execute($action, $args) {

        $this->setResponseContentType();

        $result = parent::__execute($action, $args);

        if (is_array($result)) {
            $this->response->set($result);
        } else {
            // HACK: the JSON renderer outputs an array or object-- it can't
            // handle numeric responses quite yet. Here we just append whatever
            // the F is returned and let Octopus_Renderer_AppendedContent
            // output that literal value.
            $this->response->setRenderer(new Octopus_Renderer_AppendedContent());
            $this->response->append($result === null ? '' : json_encode($result));
        }

        $this->response->stop();

        return $result;
    }

    public function success($data = array(), $status = 200) {
        $this->isError = false;
        $this->response->setStatus($status);
        return $data;
    }

    public function error($errors, $data = array(), $status = 403) {

        $this->isError = true;

        $result = array(
            'errors' => $errors,
        );

        if (is_numeric($data)) {
            $status = $data;
        } else {
            $result = array_merge($data, $result);
        }

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
        $namedArgs = array();

        if (isset($args[0])) {
            $this->resource_id = $args[0];
        } else {
            $comment = $method->getDocComment();
            if (preg_match('/@resourceRequired/', $comment)) {
                $errors['resource'] = 'resource id is required.';
            }
        }

        $args = $this->request->getInputData();

        foreach($method->getParameters() as $param) {

            $pos = $param->getPosition();
            $name = $param->getName();
            $required = !$param->isDefaultValueAvailable();
            $default = $required ? null : $param->getDefaultValue();

            $exists = array_key_exists($name, $args);

            if ($required && !$exists) {
                $errors[$name] = "$name is required.";
                continue;
            }

            if ($exists) {
                $positionalArgs[$pos] = $args[$name];
                $namedArgs[$name] = $args[$name];
            } else {
                $positionalArgs[$pos] = $default;
            }
        }

        if (count($errors)) {
            $result = $this->error($errors, 400);
            return false;
        }

        $args = $positionalArgs;
        $beforeArgs = $afterArgs = $namedArgs;
        return true;
    }

    private function findAction($originalAction, &$action, &$actionMethod) {

        $actionMethod = $this->request->getMethod() . 'Action';
        if (is_callable_and_public($this, $actionMethod)) {
            return true;
        }

        return false;

    }

    protected function isFailure($result) {

        // TODO: move the $result['success'] logic into api controller
        return $this->isError;
    }


}
