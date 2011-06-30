<?php

require_once(dirname(__FILE__) . '/TransportBase.php');
Octopus::loadClass('Http_Request');

/**
 * @group http
 *
 */
class HelperTest extends TransportBase {

    function __construct() {
        $this->class = 'Octopus_Http_Request';
    }

}


