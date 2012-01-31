<?php

/**
 * Base for implementing API controllers. Action results are returned as JSON.
 * @see ::success()
 * @see ::error()
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
    protected function success($data = array()) {
        $response = array(
            'success' => true,
        );

        if ($data) {
            $response['data'] = $data;
        }

        return $response;
    }

    /**
     * @param Mixed $errors Either a single error string or an array of
     * errors.
     * @param Mixed $data Array of data to pass down along with the errors.
     * If this is numeric, it is interpreted as $status, so you can do:
     *
     *        $this->error('Not found', 404);
     *
     * @param Number $status The HTTP status code to report. Defaults to 403
     * (forbidden).
     * @return Array A standardized JSON response array with the following
     * keys:
     *        success - Always false
     *        data -    If $data is non-null, this key will be present and will
     *                contain that value.
     *        errors -  An array of error messages. This is always an array, even
     *                   if $errors is not.
     *
     */
    protected function error($errors, $data = null, $status = 403) {

        // Support error($errors, $status)
        if (is_numeric($data)) {
            $status = $data;
            $data = null;
        }

        if (!$errors) {
            $errors = array();
        }

        if (!is_array($errors)) {
            $errors = array($errors);
        }

        $this->response->setStatus($status);

        $result = array('success' => false, 'errors' => $errors);

        // For backwards compatibility (mostly to not break a bunch of tests),
        // don't add the 'data' key unless there's actually something there
        if ($data !== null) {
            $result['data'] = $data;
        }

        return $result;
    }

    public function __execute($action, $args) {

        $this->setResponseContentType();

        $result = parent::__execute($action, $args);

        $this->response->append(json_encode($result));
        $this->response->stop();

        return $result;
    }

    /**
     * @see Octopus_Controller::resolveAction
     */
    protected function resolveAction($originalAction, &$action, &$actionMethod, &$args, &$beforeArgs, &$afterArgs, &$result) {

        if (!parent::resolveAction($originalAction, $action, $actionMethod, $args, $beforeArgs, $afterArgs, $result)) {
            return false;
        }

        if ($actionMethod === '_default') {
            // It doesn't really make sense to map parameters for _default
            return true;
        }

        // Api controllers support named arguments via $_GET or $_POST,
        // so combine those with the passed in arguments.
        if (!$args) $args = array();
        $args = array_merge($_GET, $_POST, $args); // TODO: limit to appropriate values based on http method?

        // Ensure _before and _after methods get named arguments rather than
        // positional ones
        $beforeArgs = $afterArgs = $args;

        $class = new ReflectionClass($this);
        $method = null;

        try {
            $method = $class->getMethod($actionMethod);
        } catch (Exception $ex) {
            // Method does not exist, so we can't do any mapping.
            return true;
        }

        // Translate named arguments into an array that can be passed to
        // e.g. call_user_func_array

        $errors = array();
        $positionalArgs = array();

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

            $positionalArgs[$pos] = $exists ? $args[$name] : $default;
            unset($args[$name]);
        }

        if (count($errors)) {
            $result = $this->error($errors);
            return false;
        }

        $args = $positionalArgs;

        return true;
    }

}
