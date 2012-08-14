<?php

require_once(dirname(__FILE__) . '/TransportBase.php');

/**
 * @group http
 * @group slow
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class HelperTest extends TransportBase {

    function __construct() {
        $this->class = 'Octopus_Http_Request';
    }

}


