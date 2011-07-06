<?php

Octopus::loadClass('Http_Request_Base');

class Octopus_Http_Request_Sockets extends OCtopus_Http_Request_Base {

    public function request($url, $data = null, $args = array()) {

        $this->args = array_merge($this->defaults, $args);

        list($host, $port, $path, $secure) = $this->parseUrl($url);

        $ip = gethostbyname($host);

        if ($secure) {
            $ip = 'ssl://' . $ip;
        }

        $handle = fsockopen($ip, $port);
        $request = '';
        $request .= strtoupper($this->args['method']) . " $path HTTP/{$this->args['http_version']}\r\n";
        $request .= "Host: $host\r\n";

        $headerOpts = array(
            'User-Agent',
            'Accept',
            'Accept-Encoding',
        );

        foreach($headerOpts as $opt) {
            if (isset($this->args[$opt])) {
                $request .= "$opt: {$this->args[$opt]}\r\n";
            }
        }

        $request_body = '';
        if ($data) {
            $request .= "Content-Type: application/x-www-form-urlencoded\r\n";

            foreach ($data as $key => $value) {
                $request_body .= rawurlencode($key) . '=' . urlencode($value) . '&';
            }
            $request_body = rtrim($request_body, '&');
            //$request_body .= "\r\n";
            $request .= "Content-Length: " . strlen($request_body) . "\r\n";

        }

        $request .= "Connection: close\r\n";
        $request .= "\r\n";

        $request .= $request_body;

        fwrite($handle, $request);

        $response = '';
        while (!feof($handle)) {
            $response .= fgets($handle, 8192);
        }
        fclose($handle);

        list($headers, $body) = $this->splitResponse($response);
        $this->headers = $headers;

        $body = $this->checkHeaders($body);

        return $body;

    }

    public static function usable() {
        if (!function_exists('curl_init')) {
            return false;
        }

        return true;
    }

}

