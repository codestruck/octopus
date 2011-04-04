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


}

?>
