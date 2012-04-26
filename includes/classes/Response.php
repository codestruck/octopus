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
     * Turns buffering on and off. Turning buffering off calls ::flush
     * implicitly.
     * @param Boolean $buffer Whether to buffer.
     * @return boolean|Octopus_Response If $buffer is specified, $this is
     * returned. Otherwise The current buffering state is returned.
     */
    public function buffer($buffer = null) {

        if (func_num_args() === 0) {
            return $this->isBuffered();
        }

        if (!$buffer && $this->_buffer) {
            $this->flush();
        }

        $this->_buffer = !!$buffer;
        return $this;
    }

    /**
     * @return boolean Whether this response is buffered. Buffered responses
     * are not written out to the client until ::flush() is called. Unbuffered
     * responses are written right away.
     */
    public function isBuffered() {
    	return $this->_buffer;
    }

    /**
     * Fluent accessor for ::getContentType and ::setContentType
     * @param  Mixed $type If specified, the new content type.
     * @return String|Octopus_Response If $type is specified, $this is returned.
     * Otherwise, the current content type is returned.
     * @see ::getContentType
     * @see ::setContentType
     * @deprecated Use ::getContentType and ::setContentType
     */
    public function contentType($type = null) {

        if (func_num_args() === 0) {
            return $this->getContentType();
        } else {
            return $this->setContentType($type);
        }

    }

    /**
     * @return String The Content-type header for this response.
     * @see ::setContentType
     */
    public function getContentType() {
		return $this->getHeader('Content-type', 'text/html');
    }

    /**
     * Sets the Content-type header for this response.
     * @param String $type Content type string, e.g. 'text/html' or
     * 'text/plain'.
     * @return Octopus_Response $this
     * @see ::getContentType
     */
    public function setContentType($type) {
		return $this->addHeader('Content-type', $type);
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

    /**
     * @return boolean Whether this response's content type is text/html.
     */
    public function isHtml() {
    	return !!preg_match('/^text\/html\b/i', $this->contentType());
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
        return $this->getStatus() === 403;
    }

    /**
     * @return bool Whether this response is 404 Not Found
     */
    public function isNotFound() {
        return $this->getStatus() === 404;
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
