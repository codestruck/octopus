<?php

/**
 * TODO
 * decode chunking responses
 * timeouts
 */

Octopus::loadClass('Http_Request_Sockets');
Octopus::loadClass('Http_Request_Curl');

function octopus_http_get($url, $args) {
    $http = new Octopus_Http_Request();
    return $http->request($url, null, $args);
}

function octopus_http_post($url, $data, $args) {
    $args['method'] = 'POST';
    $http = new Octopus_Http_Request();
    return $http->request($url, $data, $args);
}

class Octopus_Http_Request {

    private $transport;

    public function __construct() {
        $this->transport = $this->getTransport();
    }

    public function request($url, $data = null, $args = array()) {
        return $this->transport->request($url, $data, $args);
    }

    public function getHeaders() {
        return $this->transport->getHeaders();
    }

    private function getTransport() {
        if (Octopus_Http_Request_Curl::usable()) {
            return new Octopus_Http_Request_Curl();
        }

        if (Octopus_Http_Request_Sockets::usable()) {
            return new Octopus_Http_Request_Sockets();
        }

        throw new Octopus_Exception('Http_Request: no suitable transport found.  Check curl and security settings');

    }

}
