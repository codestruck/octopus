<?php

require_once(dirname(__FILE__) . '/TransportBase.php');

/**
 * @group http
 * @group slow
 *
 */
class SocketsTest extends TransportBase {

    function __construct() {
        $this->class = 'Octopus_Http_Request_Sockets';
    }

}


