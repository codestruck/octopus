<?php

class TransportBase extends PHPUnit_Framework_TestCase {

    function testBodyAndHeaders() {
        $url = 'http://ajax.googleapis.com/ajax/libs/jquery/1.4.1/jquery.min.js';
        $request = new $this->class();
        $content = $request->request($url);
        $headers = $request->getHeaders();

        $this->assertTrue(array_key_exists('Content-Type', $headers));
        $this->assertEquals('text/javascript; charset=UTF-8', $headers['Content-Type']);

        $this->assertEquals(70843, strlen($content));
    }

    function testBodyAndHeadersSecure() {
        $url = 'https://ajax.googleapis.com/ajax/libs/jquery/1.4.1/jquery.min.js';
        $request = new $this->class();
        $content = $request->request($url);
        $headers = $request->getHeaders();

        $this->assertTrue(array_key_exists('Content-Type', $headers));
        $this->assertEquals('text/javascript; charset=UTF-8', $headers['Content-Type']);

        $this->assertEquals(70843, strlen($content));
    }

    function testRedirect() {
        $url = 'http://graph.facebook.com/mikejestes/picture?type=small';
        $request = new $this->class();
        $content = $request->request($url);
        $headers = $request->getHeaders();

        $this->assertTrue(strlen($content) > 10);

    }

    function testPostArray() {

        $url = 'http://posttestserver.com/post.php?dump';

        $request = new $this->class();
        $content = $request->request($url, array('foo' => 12, 'bar' => 'straight up now'), array('method' => 'POST'));
        $headers = $request->getHeaders();

        $this->assertTrue(strpos($content, "key: 'foo' value: '12'") !== false);
        $this->assertTrue(strpos($content, "key: 'bar' value: 'straight up now'") !== false);
    }

    function testPostString() {

        $url = 'http://posttestserver.com/post.php?dump';

        $data = array('foo' => 12, 'bar' => 'straight up now');
        $request_body = '';
        foreach ($data as $key => $value) {
            $request_body .= rawurlencode($key) . '=' . rawurlencode($value) . '&';
        }
        $request_body = rtrim($request_body, '&');


        $request = new $this->class();
        $content = $request->request($url, $request_body, array('method' => 'POST'));
        $headers = $request->getHeaders();

        $this->assertTrue(strpos($content, "key: 'foo' value: '12'") !== false);
        $this->assertTrue(strpos($content, "key: 'bar' value: 'straight up now'") !== false);
    }

    function testGetArray() {

        $url = 'http://posttestserver.com/post.php';

        $request = new $this->class();
        $content = $request->request($url, array('dump' => 1, 'foo' => 12, 'bar' => 'straight up now'), array('method' => 'GET'));
        $headers = $request->getHeaders();

        $this->assertTrue(strpos($content, "REQUEST_URI = /post.php?dump=1&foo=12&bar=straight%20up%20now") !== false, $content);
    }

    function testGetString() {

        $url = 'http://posttestserver.com/post.php';

        $data = array('dump' => 1, 'foo' => 12, 'bar' => 'straight up now');
        $request_body = '';
        foreach ($data as $key => $value) {
            $request_body .= rawurlencode($key) . '=' . rawurlencode($value) . '&';
        }
        $request_body = rtrim($request_body, '&');

        $request = new $this->class();
        $content = $request->request($url, $request_body, array('method' => 'GET'));
        $headers = $request->getHeaders();

        $this->assertTrue(strpos($content, "REQUEST_URI = /post.php?dump=1&foo=12&bar=straight%20up%20now") !== false, $content);
    }

    function test404() {

        $url = 'http://dev.solegraphics.com/404';

        $request = new $this->class();
        $content = $request->request($url, array(), array('method' => 'GET'));
        $headers = $request->getHeaders();

        $this->assertEquals('', $content);
        $this->assertEquals(404, $request->getResponseNumber());
    }
}


