<?php

/**
 * @todo decode chunking responses
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_Http_Request {

    private $transport;

    public function __construct() {
        $this->transport = $this->getTransport();
    }

    public function __call($name, $args) {
        // passthru to public functions on transport class
        return call_user_func_array(array($this->transport, $name), $args);
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
