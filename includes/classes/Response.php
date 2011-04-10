<?php

/**
 * Class that encapsulates an HTTP response.
 */
class SG_Response {

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
    public function forbidden() {
        $this->addHeader('Status', '403 Forbidden');
        $this->_content = array();
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

        $header = $this->getHeader('Status', false);
        if (!$header) return 200;

        if (!preg_match('/^\s*(\d+)/', $header, $m)) {
            return 200;
        }

        return intval($m[1]);
    }

    public function getView() {
        return $this->_view;
    }

    public function setView($view) {
        $this->_view = $view;
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
    public function notFound() {
        $this->addHeader('Status', '404 Not Found');
    }

    /**
     * @return bool Whether this response is 403 forbidden.
     */
    public function isForbidden() {
        return $this->getStatus() == 403;
    }

}

?>
