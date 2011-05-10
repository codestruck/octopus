<?php

/**
 * Base for implementing API controllers.
 */
abstract class Octopus_Controller_Api extends Octopus_Controller {

    var $protect = true;

    public function _before($action, $args) {

        if (empty($this->protect)) {
            return true;
        } else if (is_array($this->protect)) {

            if (!in_array($action, $this->protect) && !in_array(camel_case($action), $this->protect)) {
                return true;
            }

        } else if ((strcasecmp($action, $this->protect) != 0) &&
            (strcasecmp(camel_case($action), $this->protect) != 0)) {
            return true;
        }

        return $this->__protect($action, $args);
    }

    protected function __protect($action, $args) {

        $key = octopus_api_key();
        if (empty($args['octopus_api_key']) || $args['octopus_api_key'] != $key) {
            return array('success' => false, 'errors' => array('Invalid api key.'));;
        }

        return true;
    }

    protected function buildSuccessResponse($data) {
        return array(
            'success' => true,
            'data' => $data
        );
    }

    protected function buildErrorResponse($errors) {

        if (!is_array($errors)) {
            $errors = array($errors);
        }

        return array(
            'success' => false,
            'errors' => $errors
        );
    }

    public function __execute($action, $args) {

        if (!$args) $args = array();
        $args = array_merge($args, $_GET, $_POST);

        return parent::__execute($action, $args);
    }

    protected function __executeAction($action, $args) {

        if ($action == 'defaultAction') {
            return parent::__executeAction($action, $args);
        }

        $class = new ReflectionClass($this);
        $method = $class->getMethod($action);

        $positionalArgs = array();
        $errors = array();

        foreach($method->getParameters() as $param) {

            $pos = $param->getPosition();
            $name = $param->getName();
            $required = !$param->isDefaultValueAvailable();
            $default = $required ? null : $param->getDefaultValue();

            if ($required && !isset($args[$name])) {
                $errors[$name] = "$name is required.";
                continue;
            }

            $positionalArgs[$pos] = isset($args[$name]) ? $args[$name] : $default;
        }

        if (count($errors)) {
            return $this->buildErrorResponse($errors);

        }

        return call_user_func_array(array($this, $action), $positionalArgs);
    }

}


?>
