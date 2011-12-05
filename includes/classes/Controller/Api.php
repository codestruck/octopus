<?php

/**
 * Base for implementing API controllers.
 */
abstract class Octopus_Controller_Api extends Octopus_Controller {

    private static $contentTypes = array(
        'json' => 'application/json',
        'jsonp' => 'text/javascript',
        'text' => 'text/html'
    );

    protected function setResponseContentType() {

        $format = strtolower(trim(get('octopus_api_format', '')));
        if ($format && isset(self::$contentTypes[$format])) {
            $this->response->contentType(self::$contentTypes[$format]);
        }

        // TODO: Detect JSONP

        $this->response->contentType(self::$contentTypes['json']);
    }

    public function _default($action, $args) {
        return $this->error("Invalid action: $action");
    }

    /**
     * @return Array A standardized JSON response array.
     */
    protected function success($data) {
        return array(
            'success' => true,
            'data' => $data
        );
    }

    /**
     * @return Array A standardized JSON response array.
     */
    protected function error($errors) {

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

        $this->setResponseContentType();

        $data = parent::__execute($action, $args);

        $dumped_content = get_dumped_content();

        if (!empty($dumped_content)) {
            if ($data === null) {
                $data = $dumped_content;
            } else if (is_array($data)) {
                $data = array_merge($dumped_content, $data);
            } else {
                $data = array_merge($dumped_content, compact('data'));
            }
            output_dumped_content_header(array_pop($dumped_content), $this->response);
        }

        $this->response->append(json_encode($data));
        $this->response->stop();

        return $data;
    }

    protected function __executeAction($action, $actionMethod, $args) {

        if ($action == '_default') {
            return parent::__executeAction($action, $actionMethod, $args);
        }

        $class = new ReflectionClass($this);
        $method = $class->getMethod($actionMethod);

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
            unset($args[$name]);
        }

        if (count($errors)) {
            return $this->error($errors);

        }

        // Append all remaining args to the end
        // TODO test
        $positionalArgs[] = $args;

        return parent::__executeAction($action, $actionMethod, $positionalArgs);
    }

}


?>
