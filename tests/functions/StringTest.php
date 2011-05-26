<?php

/**
 * @group core
 * @group string
 */
class StringTests extends PHPUnit_Framework_TestCase
{
    function testEndIn() {

        $this->assertEquals(
            'foo/',
            end_in('/', 'foo'),
            'failed when needing to append'
        );

        $this->assertEquals(
            'foo/',
            end_in('/', 'foo/'),
            'failed when not needing to append'
        );

    }

    function testStartIn() {

        $this->assertEquals(
            '/foo',
            start_in('/', 'foo'),
            'failed when needing to prepend'
        );

        $this->assertEquals(
            '/foo',
            start_in('/', '/foo'),
            'failed when not needing to prepend'
        );

    }

    function testH() {

        $this->assertEquals(
            '&lt;b&gt;Foo&lt;/b&gt;',
            h('<b>Foo</b>')
        );

        $this->assertEquals(
            '&lt;b&gt;Foo&lt;/b&gt;',
            h('<b>', 'Foo', '</b>')
        );
    }

    function testParseRegex() {

        $r = parse_regex('/whatever/i');
        $this->assertEquals('whatever', $r['pattern']);
        $this->assertEquals('i', $r['flags']);

        $this->assertFalse(parse_regex('/whatever'));

    }

    function testNormalizeSql() {

        $this->assertEquals(
            "SELECT * FROM `foo` WHERE id = 42 AND name = 'whatever'",
            normalize_sql(
                "SELECT * FROM `foo` WHERE id = ? AND name = ?",
                array(42, 'whatever')
            )
        );

    }

    function testToSlug() {

        $tests = array(
          'My first slug' => 'my-first-slug',
          'foo & BAR slug' => 'foo-and-bar-slug',
          "it's a good thing" => 'its-a-good-thing',
          'multiple   spaces   ' => 'multiple-spaces'
        );

        foreach($tests as $input => $expected) {

            $this->assertEquals(
                $expected,
                to_slug($input)
            );

            $this->assertEquals($expected, to_slug($input, 0));
            $this->assertEquals($expected, to_slug($input, 1));

            $this->assertEquals($expected . '-2', to_slug($input, 2));
        }

    }

    function testCamelCase() {

        $tests = array(

            'camel_cased' => 'camelCased',
            'CAMEL__cased' => 'camelCased',
            'camel-CASED' => 'camelCased',
            '  camel  CASED  ' => 'camelCased',
            'CamelCased' => 'camelCased',
            'camel-cased' => 'camelCased'

         );

        foreach($tests as $input => $expected) {
            $this->assertEquals(
                $expected,
                camel_case($input),
                'failed on ' . $input
            );
        }
    }

    function testPluralize() {

        $tests = array(

            'product' => 'products',
            'category' => 'categories',
            'products' => 'products',
            'categories' => 'categories',
            'boy' => 'boys',
            'way' => 'ways',

         );

        foreach($tests as $input => $expected) {
            $this->assertEquals(
                $expected,
                pluralize($input),
                'failed on ' . $input
            );
        }
    }

    function testSingularize() {

        $tests = array(

            'product' => 'product',
            'products' => 'product',
            'categories' => 'category',
            'category' => 'category',

        );

        foreach($tests as $input => $expected) {
            $this->assertEquals($expected, singularize($input));
        }

    }


}

?>
