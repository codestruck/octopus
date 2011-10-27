<?php

/**
 * @group http
 */
class HttpFunctionsTest extends PHPUnit_Framework_TestCase {

    function testHttpBuildQuery() {

        $data = array('foo' => 1, 'bar' => 'words with spaces');
        $this->assertEquals('foo=1&bar=words%20with%20spaces', octopus_http_build_query($data));
        $this->assertEquals('foo=1&amp;bar=words%20with%20spaces', octopus_http_build_query($data, '&amp;'));
        $this->assertEquals('foo=1&bar=words+with+spaces', octopus_http_build_query($data, '&', 'POST'));

    }

}


