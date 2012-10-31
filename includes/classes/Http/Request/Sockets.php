<?php

/**
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_Http_Request_Sockets extends Octopus_Http_Request_Base {

    public function request($url, $data = null, $args = array()) {

        // TODO: Support check_ssl arg to disable SSL cert verification

        $this->requestUrl = $url;
        $this->requestData = $data;
        $this->args = array_merge($this->defaults, $args);

        list($host, $port, $path, $secure, $protocol, $user, $pass) = $this->parseUrl($url, $data);

        $ip = gethostbyname($host);

        if ($secure) {
            $sslProto = 'ssl';
            if (!empty($args['ssl_version'])) {
                $sslProto = 'sslv3';
            }

            $protos = stream_get_transports();
            if (!in_array($sslProto, $protos)) {
                throw new Octopus_Exception("No $sslProto Support in fsockopen");
            }

            $ip = $sslProto . '://' . $ip;
        }

        $timeout = ini_get("default_socket_timeout");

        if (!empty($args['timeout'])) {
            $timeout = $args['timeout'];
        }

        $handle = stream_socket_client($ip . ':' . $port, $errno, $errstr, $timeout);
        if (!$handle) {
            throw new Octopus_Exception("Could not create socket: $errno, $errstr");
        }

        if (!empty($args['timeout'])) {
            socket_set_timeout($handle, $args['timeout']);
        }

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
            if (!isset($this->args['Content-Type'])) {
                $request .= "Content-Type: application/x-www-form-urlencoded\r\n";
            }

            if (is_array($data)) {
                $request_body = octopus_http_build_query($data, '&', 'POST');
            } else {
                $request_body = $data;
            }

            $request .= "Content-Length: " . strlen($request_body) . "\r\n";

        }

        if ($user) {
            $auth = base64_encode("$user:$pass");
            $request .= "Authorization: Basic $auth\r\n";
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

        if (isset($this->headers['Transfer-Encoding']) && $this->headers['Transfer-Encoding'] === 'chunked') {
            $body = compat_http_chunked_decode($body);
        }

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

