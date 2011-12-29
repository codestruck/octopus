<?php

class Octopus_Http_Request_Base {

    protected $args;
    protected $raw_headers;
    protected $headers;
    protected $defaults;
    protected $responseStatus;
    protected $responseNumber;

    public function __construct() {
        $this->defaults = array(
            'method' => 'GET',
            'max_redirects' => 5,
            'http_version' => '1.1',
            'User-Agent' => 'Mozilla/5.0 (X11; Linux i686; rv:5.0) Gecko/20100101 Firefox/5.0',
            'Accept-Encoding' => 'gzip,deflate',
            'Accept' => '*/*',
        );

        $this->reservedOpts = array(
            'max_redirects',
            'method',
            'http_version',
        );

    }

    public function getHeaders() {
        return $this->headers;
    }

    public function getResponseStatus() {
        return $this->responseStatus;
    }

    public function getResponseNumber() {
        return $this->responseNumber;
    }

    protected function parseUrl($url, $queryArgs) {

        $urlInfo = parse_url($url);

        $host = $urlInfo['host'];

        $port = $urlInfo['scheme'] == 'http' ? 80 : 443;
        if (isset($urlInfo['port'])) {
            $port = $urlInfo['port'];
        }

        $path = isset($urlInfo['path']) ? $urlInfo['path'] : '/';
        if (isset($urlInfo['query'])) {
            $path .= '?' . $urlInfo['query'];
        }
        if (isset($urlInfo['fragment'])) {
            $path .= '#' . $urlInfo['fragment'];
        }

        $secure = ($urlInfo['scheme'] == 'https');

        if (strtoupper($this->args['method']) == 'GET' && $queryArgs) {

            if (is_array($queryArgs)) {
                $queryArgs = octopus_http_build_query($queryArgs, '&');
            }

            if (strpos($path, '?') === false) {
                $path .= '?' . $queryArgs;
            } else {
                $path = end_in('&', $path);
                $path .= $queryArgs;
            }

        }

        return array($host, $port, $path, $secure, $urlInfo['scheme']);

    }

    protected function splitResponse($response) {
        $parts = explode("\n", $response);
        $headers = array();
        $body = array();
        $header_mode = true;

        $top = array_shift($parts);
        $this->responseStatus = substr($top, strpos($top, ' ') + 1);
        $this->responseNumber = substr($this->responseStatus, 0, strpos($this->responseStatus, ' '));

        foreach ($parts as $oline) {
            $line = trim($oline);

            if ($header_mode) {
                $colonPos = strpos($line, ':');

                if ($colonPos !== false) {
                    $key = substr($line, 0, $colonPos);
                    $value = substr($line, $colonPos + 1);
                    $value = trim($value);
                    $headers[$key] = $value;
                }

            } else {
                $body[] = $oline;
            }

            if ($line == '') {
                $header_mode = false;
            }

        }

        $body = implode("\n", $body);

        return array($headers, $body);

    }

    protected function checkHeaders($content) {
        if (isset($this->headers['Location'])) {

            $args = $this->args;
            $args['max_redirects'] = $args['max_redirects'] - 1;

            if ($args['max_redirects'] < 1) {
                throw new Octopus_Exception('Max redirects exceeded in Http Request');
                $this->headers = '';
                return '';
            }

            $content = $this->request($this->headers['Location'], array(), $args);

        }

        if (isset($this->headers['Content-Encoding'])) {
            if ($this->headers['Content-Encoding'] == 'gzip') {
                $content = gzinflate(substr($content, 10));
            }
            if ($this->headers['Content-Encoding'] == 'deflate') {
                $content = gzinflate($content);
            }
        }

        // the 400's and 500's are bad news
        if ($this->responseNumber >= 400) {
            if (DEV) {
                $message = "error {$this->responseNumber}\nURL: {$this->requestUrl}\n";
                if ($this->requestData) {
                    $message .= "params: " . print_r($this->requestData, 1);
                }
                $message .= $content;

                $log = new Octopus_Logger_File(OCTOPUS_PRIVATE_DIR . 'http.log');
                $log->log($message);
            }

            $content = '';
        }

        return $content;
    }

}

