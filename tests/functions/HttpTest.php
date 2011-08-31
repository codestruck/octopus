<?php

/**
 * @group core
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


    }

    function testChangeUrlQueryString() {

        foreach(array('/', 'http://') as $prefix) {

            $url = "{$prefix}whatever?foo=bar&action&unchanged=1&q=matt&shoulddisappear=3";
            $this->assertEquals(
                "{$prefix}whatever?foo=baz&action=search&unchanged=1&q=hinz",
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

}

?>
