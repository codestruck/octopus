<?php

/**
 * Class that encapsulates an HTTP response.
 */
class Octopus_Response {

    private $_status = null;
    private $_headers = array();
    private $_content = array();
    private $_flushedHeaders = array();

    /**
     * Adds content to the response.
     */
    public function &append($content) {
        $this->_content[] = $content;
        return $this;
    }

    public function &addHeader($name, $value) {
        $this->_headers[$name] = $value;
        return $this;
    }

    /**
     * Writes any pending headers and content.
     */
    public function &flush() {

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
    public function &forbidden() {
        return
            $this
                ->reset()
                ->setStatus('HTTP/1.1 403 Forbidden');
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
    public function &setStatus($status) {

        if (is_numeric($status)) {
            $status = 'HTTP/1.1 ' . $status;
        }

        $this->_status = $status;

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

    public function &removeHeader($name) {
        unset($this->_headers[$name]);
        return $this;
    }

    /**
     * Helper that marks the response as a 404.
     */
    public function &notFound() {
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
    public function &redirect($to, $permanent = false) {

        $this
            ->reset()
            ->setStatus('HTTP/1.1 ' . ($permanent ? '301 Moved Permanently' : '302 Found'))
            ->addHeader('Location', $to);

        return $this;
    }

    /**
     * Clears the response.
     */
    private function &reset() {
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

        $status = $this->getStatus();

        switch($status) {

            case 301:
            case 302:
            case 307:
                return false;

            default:
                return true;
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

}

?>
