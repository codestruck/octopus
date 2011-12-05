<?php

/**
 * Class that encapsulates an HTTP response.
 */
class Octopus_Response {

    private $_active = true;
    private $_buffer = false;
    private $_status = null;
    private $_headers = array();
    private $_content = array();
    private $_flushedHeaders = array();

    public function __construct($buffer = false) {
        $this->_buffer = $buffer;
    }

    /**
     * Adds content to the response.
     */
    public function append(/* $content */) {

        if (!$this->_active) {
            return $this;
        }

        $args = func_get_args();
        foreach($args as $arg) {

            if ($this->_buffer) {
                $this->_content[] = $arg;
            } else {
                echo $arg;
            }
        }

        return $this;
    }

    public function addHeader($name, $value) {

        if (!$this->_active) {
            return $this;
        }

        if ($this->_buffer) {
            $this->_headers[$name] = $value;
        } else {
            header("$name: $value");
        }

        return $this;
    }

    /**
     * Turns buffering on and off.
     */
    public function buffer(/* $buffer */) {

        if (func_num_args() == 0) {
            return $this->_buffer;
        }

        $buffer = func_get_args(0);
        if (!$buffer && $this->_buffer) {
            $this->flush();
        }

        $this->_buffer = !!$buffer;
        return $this;
    }

    public function contentType(/* $contentType */) {

        if (func_num_args() == 0) {
            return $this->getHeader('Content-type', 'text/html');
        } else {
            $contentType = func_get_arg(0);
            return $this->addHeader('Content-type', $contentType);
        }

    }

    /**
     * Writes any pending headers and content.
     */
    public function flush() {

    	if ($this->_status && empty($this->_flushedHeaders['status'])) {
    		header($this->_status);
    		$this->_flushedHeaders['status'] = $this->_status;
    		$this->_status = null;
    	}

        foreach($this->_headers as $key => $value) {
            if (!isset($this->_flushedHeaders[$key])) {
                header("$key: $value");
                $this->_flushedHeaders[$key] = true;
            }
        }

        while(count($this->_content)) {
            echo array_shift($this->_content);
        }

        return $this;
    }

    /**
     * Sets the status header to 403/Forbidden and clears the output buffer.
     */
    public function forbidden($clearBuffer = true) {

        if ($clearBuffer) {
            $this->reset();
        }

        return $this->setStatus('HTTP/1.1 403 Forbidden');
    }

    /**
     * @return String All content that has been added to this response since
     * the last flush.
     */
    public function getContent() {

        $output = '';

        foreach($this->_content as $c) {
            $output .= $c;
        }

        return $output;
    }

    /**
     * @return Number The numeric value of the Status header, e.g. 404 or 200.
     */
    public function getStatus() {

        if (preg_match('#^\s*(HTTP\s*[\\/]?\s*\d(\.\d)?\s*)?(\d+)#i', $this->_status, $m)) {
            return intval($m[3]);
        }

        return 200;
    }

    /**
     * Sets the status line for this response.
     */
    public function setStatus($status) {

        if (!$this->_active) {
            return $this;
        }

        if (is_numeric($status)) {
            $status = 'HTTP/1.1 ' . $status;
        }

        if ($this->_buffer) {
            $this->_status = $status;
        } else {
            header($status);
        }

        return $this;
    }

    public function getHeader($name, $default = null) {
        if (isset($this->_headers[$name])) {
            return $this->_headers[$name];
        }
        return $default;
    }

    /**
     * @return Array All headers set for this response.
     */
    public function getHeaders() {
        return $this->_headers;
    }

    /**
     * @return bool Whether any headers have been set.
     */
    public function haveHeaders() {
        return !empty($this->_headers);
    }

    public function removeHeader($name) {
        unset($this->_headers[$name]);
        return $this;
    }

    /**
     * Helper that marks the response as a 404.
     */
    public function notFound() {
        return $this
            ->reset()
            ->setStatus('HTTP/1.1 404 Not Found');
    }

    /**
     * @return bool Whether this response is 403 forbidden.
     */
    public function isForbidden() {
        return $this->getStatus() == 403;
    }

    /**
     * @return bool Whether this response is 404 Not Found
     */
    public function isNotFound() {
        return $this->getStatus() == 404;
    }

    /**
     * Clears the response and redirects the user to a new location.
     * @param $to string URL to redirect to.
     * @param $permanent bool Whether this is a permanent redirect.
     */
    public function redirect($to, $permanent = false) {

        return $this
            ->reset()
            ->setStatus('HTTP/1.1 ' . ($permanent ? '301 Moved Permanently' : '302 Found'))
            ->addHeader('Location', $to)
            ->stop();
    }

    /**
     * Does a str_replace across all buffered content.
     * @throws Octopus_Exception if response is not buffered.
     */
    public function replaceContent($search, $replace) {

        if (!$this->_buffer) {
            throw new Octopus_Exception("Octopus_Response::replaceContent can't be called on unbuffered responses");
        }

        $count = 0;
        $content = implode("\n", $this->_content);
        $content = str_replace($search, $replace, $content, $count);
        $this->_content = array($content);


        return $this;
    }

    /**
     * Clears the response.
     */
    private function reset() {
        $this->_status = null;
        $this->_headers = array();
        $this->_content = array();
        $this->_flushedHeaders = array();
        return $this;
    }

    /**
     * @return bool Whether the app should bother continuing to process this
     * request.
     */
    public function shouldContinueProcessing() {
        return $this->_active;
    }

    /**
     * Stops processing. For buffered responses, this sets a flag and marks the
     * response as read-only. For unbuffered responses, calls exit().
     */
    public function stop() {
        if ($this->_buffer) {
            $this->_active = false;
        } else {
            exit();
        }
    }

    public function __toString() {

        $result = ($this->_status ? $this->_status : 'HTTP/1.1 200 OK');

        foreach($this->_headers as $name => $content) {
            $result .= "\n$name: $content";
        }
        $result .= "\n\n";
        $result .= $this->getContent();

        return $result;
    }

    /**
     * @return A reference to the current response, if any is in progress.
     */
    public static function current() {

        if (!Octopus_App::isStarted()) {
        	return null;
        }

        $app = Octopus_App::singleton();
        return $app->getCurrentResponse();

    }

}

?>
