<?php

/**
 * @group core
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class HttpTests extends PHPUnit_Framework_TestCase
{

    function testExpandUrls() {

        $options = array('HTTP_HOST' => 'myhost');

        $html = <<<END
Lorem ipsum <a href="/path/to/something">link</a> and also
here is an image: <img src="/path/to/image.jpg" /> and
how about a full url: <a href="http://www.google.com/">google</a>.
END;

        $expected = <<<END
Lorem ipsum <a href="http://myhost/path/to/something">link</a> and also
here is an image: <img src="http://myhost/path/to/image.jpg" /> and
how about a full url: <a href="http://www.google.com/">google</a>.
END;

        $this->assertEquals(
            $expected,
            expand_relative_urls($html, null, $options)
        );

    }

    function testMakeUrl() {

        $this->assertEquals(
            'foo',
            u('foo'),
            'should keep relative paths in tact!'
        );

        $this->assertEquals(
            'http://www.google.com',
            u('http://www.google.com'),
            'should keep full urls in tact'
        );

        $this->assertEquals(
            '/test/foo',
            u('/foo', null, array('URL_BASE' => '/test/')),
            'Should prepend URL_BASE to absolute paths.'
        );

        $this->assertEquals(
            '://www.google.com',
            u('://www.google.com'),
            'should support "://"'
        );

        $this->assertEquals(
            '#',
            u('#'),
            'should not do anything to #links'
        );

        $this->assertEquals(
            'http://www.google.com/?one=foo',
            u('http://www.google.com', array('one' => 'foo')),
            'Add slash with args'
        );

        $this->assertEquals(
            '/test/foo?color=has%20space',
            u('/test/foo', array('color' => 'has space')),
            'proper escaping of spaces'
        );

        $this->assertEquals(
            '/test/foo?one=foo&two=has%20space',
            u('/test/foo', array('one' => 'foo', 'two' => 'has space')),
            'default short ampersands'
        );

        $this->assertEquals(
            '/test/foo?one=foo&amp;two=has%20space',
            u('/test/foo', array('one' => 'foo', 'two' => 'has space'), array('html' => true)),
            'specify html style expanded ampersands'
        );

    }

    function testFindUrlBase() {

        $this->assertEquals(
            '/',
            find_url_base('/var/www/', '/var/www/'),
            'when doc_root == root_dir, base should be slash'
        );

        $this->assertEquals(
            '/subdir/',
            find_url_base('/var/www/subdir/', '/var/www/'),
            'should detect when running in subdir.'
        );

        $this->assertEquals(
            false,
            find_url_base('/var/www/', '/some/weird/path/'),
            'Should return false when not running off document_root'
        );

        unset($_SERVER['DOCUMENT_ROOT']);
        $this->assertEquals('/', find_url_base('/my/root/dir/'), "should be / when doc root is unknown");


    }

    function testChangeUrlQueryString() {

        foreach(array('/', 'http://') as $prefix) {

            $expect = "{$prefix}whatever?foo=baz&action=search&unchanged=1&q=hinz";
            if ($prefix != '/') {
                $expect = "{$prefix}whatever/?foo=baz&action=search&unchanged=1&q=hinz";
            }

            $url = "{$prefix}whatever?foo=bar&action&unchanged=1&q=matt&shoulddisappear=3";
            $this->assertEquals(
                $expect,
                u(
                    $url,
                    array(
                        'foo' => 'baz',
                        'action' => 'search',
                        'q' => 'hinz',
                        'shoulddisappear' => null
                    )
                ),
                "prefix: $prefix"
            );
        }

    }

    function testOverwriteUrlQueryString() {

        foreach(array('/', 'http://') as $prefix) {

            $url = "{$prefix}whatever?foo=bar&action&unchanged=1&q=matt&shoulddisappear=3";
            $this->assertEquals(
                "{$prefix}whatever?foo=baz&action=search&q=hinz",
                u(
                    $url,
                    array(
                        'foo' => 'baz',
                        'action' => 'search',
                        'q' => 'hinz',
                        'shoulddisappear' => null
                    ),
                    array('replace_qs' => true)
                ),
                "prefix: $prefix"
            );
        }

    }

    function testRemoveUrlQueryString() {

        foreach(array('/', 'http://') as $prefix) {

            $url = "{$prefix}whatever?foo=bar&action&unchanged=1&q=matt&shoulddisappear=3";
            $this->assertEquals(
                "{$prefix}whatever",
                u($url, array(), array('replace_qs' => 1)),
                "prefix: $prefix"
            );
        }

    }

    function testDetectUrlBaseAlreadyApplied() {

        $url_base = '/foo/';
        $url = "{$url_base}whatever";

        $this->assertEquals(
            $url,
            u($url, null, array('URL_BASE' => $url_base))
        );

    }

    function testGetFullUrl() {

        $tests = array(

            'http://myserver.com/subdir/foo/bar' => array(
                'input' => '/foo/bar',
                'message' => 'Basic path',
                '_SERVER' => array(
                    'HTTP_HOST' => 'myserver.com',
                ),
                'URL_BASE' => '/subdir/'
            ),

            'https://myserver.com/subdir/foo/bar' => array(
                'input' => '/foo/bar',
                'message' => '$_SERVER[HTTPS] = on',
                '_SERVER' => array(
                    'HTTPS' => 'on',
                    'HTTP_HOST' => 'myserver.com'
                ),
                'URL_BASE' => '/subdir/'
            ),

            'https://myserver.com/subdir/foo/bar' => array(
                'input' => '/subdir/foo/bar',
                'message' => 'dont duplicate url_base',
                '_SERVER' => array(
                    'HTTP_HOST' => 'myserver.com',
                    'HTTPS' => 'on'
                ),
                'URL_BASE' => '/subdir/'
            ),

            'https://myserver.com/subdir/foo/bar' => array(
                'input' => 'http://myserver.com/subdir/foo/bar',
                'message' => 'bump up to https',
                '_SERVER' => array(
                    'HTTP_HOST' => 'myserver.com',
                    'HTTPS' => 'on'
                ),
                'URL_BASE' => '/subdir/'
            ),

            'http://www.google.com/?q=test' => array(
                'input' => 'http://www.google.com/?q=test',
                'message' => 'preserve offsite links',
                '_SERVER' => array(
                    'HTTP_HOST' => 'myserver.com',
                    'HTTPS' => 'on'
                ),
                'URL_BASE' => '/subdir/'
            ),

            'http://myserver.com:1337/subdir/foo/bar' => array(
                'input' => '/foo/bar',
                'message' => 'handle running on nonstandard port (http)',
                '_SERVER' => array(
                    'HTTP_HOST' => 'myserver.com',
                    'SERVER_PORT' => 1337,
                ),
                'URL_BASE' => '/subdir/'
            ),

            'https://myserver.com:1337/subdir/foo/bar' => array(
                'input' => '/foo/bar',
                'message' => 'handle running on nonstandard port (https)',
                '_SERVER' => array(
                    'HTTP_HOST' => 'myserver.com',
                    'HTTPS' => 'on',
                    'SERVER_PORT' => 1337,
                ),
                'URL_BASE' => '/subdir/'
            ),
        );

        foreach($tests as $expected => $params) {

          $ogHost = (isset($_SERVER['HTTP_HOST'])) ? $_SERVER['HTTP_HOST'] : null;

          foreach($_SERVER as $key => $value) {
              // phpunit complains if REQUEST_TIME is unset
              if ($key !== 'REQUEST_TIME') {
                unset($_SERVER[$key]);
            }
          }

          if (!empty($params['_SERVER'])) {
              foreach($params['_SERVER'] as $key => $value) {
                  $_SERVER[$key] = $value;
              }
          }

          $this->assertEquals($expected, get_full_url($params['input'], $params), $params['message']);

          if ($ogHost === null) {
              unset($_SERVER['HTTP_HOST']);
          } else {
              $_SERVER['HTTP_HOST'] = $ogHost;
          }
        }

    }

    /**
     * @expectedException Octopus_Exception
     */
    function testGetFullUrlWithRelativePathThrowsException() {

        get_full_url('some/relative/path');

    }

    function testMakeExternalUrl() {

        $this->assertEquals(
            'http://www.google.com',
            make_external_url('http://www.google.com'),
            'should keep full urls in tact'
        );

        $this->assertEquals(
            'https://www.google.com',
            make_external_url('https://www.google.com'),
            'should keep secure urls in tact'
        );

        $this->assertEquals(
            'https://www.google.com',
            make_external_url('https://www.google.com', true),
            'should keep secure urls in tact'
        );

        $this->assertEquals(
            'https://www.google.com',
            make_external_url('http://www.google.com', true),
            'Promoting to secure url'
        );

        $this->assertEquals(
            'http://www.google.com',
            make_external_url('https://www.google.com', false),
            'removing https'
        );

        $this->assertEquals(
            '',
            make_external_url(''),
            'blank should be blank'
        );


    }

   function testGetUserIp() {

        $_SERVER['HTTP_X_FORWARDED_FOR'] = '10.0.0.4';
        $_SERVER['REMOTE_ADDR'] = '10.0.0.5';
        $this->assertEquals('10.0.0.4', get_user_ip());

        $_SERVER['REMOTE_ADDR'] = '10.0.0.5';
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        $this->assertEquals('10.0.0.5', get_user_ip());

        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        unset($_SERVER['REMOTE_ADDR']);
        $this->assertEquals('127.0.0.1', get_user_ip());

    }


}
