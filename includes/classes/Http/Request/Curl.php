<?php

Octopus::loadClass('Http_Request_Base');

class Octopus_Http_Request_Curl extends Octopus_Http_Request_Base {

    public function request($url, $data = null, $args = array()) {

        $this->args = array_merge($this->defaults, $args);

        list($host, $port, $path, $secure, $protocol) = $this->parseUrl($url, $data);
        $url = $protocol . '://' . $host . $path;

        $handle = curl_init();

        curl_setopt($handle, CURLOPT_URL, $url);
        curl_setopt($handle, CURLOPT_HEADER, false);
        curl_setopt($handle, CURLOPT_HEADERFUNCTION, array(&$this, 'receive_headers'));
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_FOLLOWLOCATION, false);

        if (strtolower($this->args['method']) == 'post') {
            curl_setopt($handle, CURLOPT_POST, true);

            if ($data) {

                if (is_array($data)) {
                    $data = octopus_http_build_query($data, '&', 'POST');
                }

                curl_setopt($handle, CURLOPT_POSTFIELDS, $data);
            }

        }

        if ($this->args['http_version'] === '1.0') {
            curl_setopt($handle, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        } else {
            curl_setopt($handle, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        }

        if (isset($this->args['User-Agent'])) {
            curl_setopt($handle, CURLOPT_USERAGENT, $this->args['User-Agent']);
        }

        if (isset($this->args['Accept-Encoding'])) {
            curl_setopt($handle, CURLOPT_ENCODING, $this->args['Accept-Encoding']);
        }

        $headerOpts = array(
            'Accept',
        );

        $request_headers = array();

        foreach($headerOpts as $opt) {
            if (isset($this->args[$opt])) {
                $request_headers[$opt] = $this->args[$opt];
            }
        }

        if (count($request_headers)) {
            curl_setopt($handle, CURLOPT_HTTPHEADER, $request_headers);
        }

        $this->headers = array();
        $this->raw_headers = '';

        $body = curl_exec($handle);
        $info = curl_getinfo($handle);
        curl_close($handle);

        list($headers, $empty_body) = $this->splitResponse($this->raw_headers);
        $this->headers = $headers;
        unset($this->headers['Content-Encoding']);

        $body = $this->checkHeaders($body);

        return $body;

    }

    public function receive_headers($curl_handle, $header_portion) {
        $this->raw_headers .= $header_portion;
        return strlen($header_portion);
    }

    public static function usable() {
        if (!function_exists('curl_init')) {
            return false;
        }

        return true;
    }


}

