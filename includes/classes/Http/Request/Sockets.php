<?php

class Octopus_Http_Request_Sockets extends Octopus_Http_Request_Base {

    public function request($url, $data = null, $args = array()) {

        $this->requestUrl = $url;
        $this->requestData = $data;
        $this->args = array_merge($this->defaults, $args);

        list($host, $port, $path, $secure) = $this->parseUrl($url, $data);

        $ip = gethostbyname($host);

        if ($secure) {
            $protos = stream_get_transports();
            if (!in_array('ssl', $protos)) {
                throw new Octopus_Exception('No SSL Support in fsockopen');
            }
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

        foreach($this->args as $opt => $value) {
            if (!in_array($opt, $this->reservedOpts)) {
                $request .= "$opt: {$value}\r\n";
            }
        }

        $request_body = '';
        if ($data) {
            $request .= "Content-Type: application/x-www-form-urlencoded\r\n";

            if (is_array($data)) {
                $request_body = octopus_http_build_query($data, '&', 'POST');
            } else {
                $request_body = $data;
            }

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
        if (ini_get('allow_url_fopen') == false) {
            return false;
        }

        return true;
    }

}

