<?php

/**
 * @group core
 * @group string
 */
class StringTests extends PHPUnit_Framework_TestCase
{

    function testGlobToRegex() {

        $tests = array(
            '' => '',
            'foo.bar' => '^foo\.bar$',
            '*.txt' => '^.+\.txt$',
            '/path/to/*.txt' => '^/path/to/.+\.txt$',
            'foo.???' => '^foo\....$',
            '[abc][def].*' => '^[abc][def]\..+$'
        );

        foreach($tests as $input => $expected) {
            $this->assertEquals($expected, glob_to_regex($input), "Failed on '$input'");
        }

    }

    function testToTableName() {

        $tests = array(
            '' => '',
            'foo' => 'foos',
            '  foo  ' => 'foos',
            'FOO' => 'foos',
            'Foo' => 'foos',
            'Foos' => 'foos',
            'categories' => 'categories',
            'category' => 'categories'
        );

        foreach($tests as $input => $expected) {
            $this->assertEquals($expected, to_table_name($input), "Failed on '$input'");
        }

    }

    function testToID() {

        $tests = array(
            '' => '',
            'foo' => 'foo_id',
            '  foo  ' => 'foo_id',
            'FOO' => 'foo_id',
            'Foo' => 'foo_id',
            'Foos' => 'foo_id',
            'categories' => 'category_id'
        );

        foreach($tests as $input => $expected) {
            $this->assertEquals($expected, to_id($input), "Failed on '$input'");
        }

    }

    function testStartsWith() {

        $tests = array(
            array('foo', 'f', true, 'oo'),
            array('foo', '', true, 'foo'),
            array('foo', 'foobar', false, null),
            array('/path/to/whatever', '/path/to', true, '/whatever'),
            array('FOO', 'f', true, 'OO', true)
        );

        foreach($tests as $t) {

            $x = array_shift($t);
            $y = array_shift($t);
            $expected = array_shift($t);
            $expectedRemainder = array_shift($t);
            $r = null;
            $ignoreCase = array_shift($t);

            $this->assertEquals($expected, starts_with($x, $y, $ignoreCase, $r), "Failed on $x, $y");
            $this->assertEquals($expectedRemainder, $r, "Remainder is wrong for $x, $y");

        }
    }

    function testEndsWith() {

        $tests = array(
            array('foo', 'o', true, 'fo'),
            array('foo', '', true, 'foo'),
            array('foo', 'foobar', false, null),
            array('/path/to/whatever', 'whatever', true, '/path/to/'),
            array('FOO', 'oo', true, 'F', true)
        );

        foreach($tests as $t) {

            $x = array_shift($t);
            $y = array_shift($t);
            $expected = array_shift($t);
            $expectedRemainder = array_shift($t);
            $r = null;
            $ignoreCase = array_shift($t);

            $this->assertEquals($expected, ends_with($x, $y, $ignoreCase, $r), "Failed on $x, $y");
            $this->assertEquals($expectedRemainder, $r, "Remainder is wrong for $x, $y");

        }

    }

    function testWildcardify() {

        $tests = array(
            'foo' => '%foo%',
            'f?o' => 'f_o',
            'foo%' => '%foo\%%',
            'foo_bar' => '%foo\_bar%',
            'foo \ bar' => '%foo \\\\ bar%',
            'f*' => 'f%'
        );

        foreach($tests as $input => $expected) {
            $this->assertEquals($expected, wildcardify($input), "Failed on $input");
        }

        $this->assertEquals('foo', wildcardify('foo', false));

    }

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

        $tests = array(
            '<b>Foo</b>' => '&lt;b&gt;Foo&lt;/b&gt;',
            '<b class="test">Foo</b>' => '&lt;b class=&quot;test&quot;&gt;Foo&lt;/b&gt;',
            '<b class=\'test\'>Foo</b>' => '&lt;b class=&#039;test&#039;&gt;Foo&lt;/b&gt;'
        );

        foreach($tests as $input => $expected) {
            $this->assertEquals($expected, h($input), "Failed on '$input'");
        }

        $this->assertEquals('thisshouldconcat', h('this', 'should', 'concat'));
    }

    function testParseRegex() {

        $r = parse_regex('/whatever/i');
        $this->assertEquals('whatever', $r['pattern']);
        $this->assertEquals('i', $r['flags']);

        $this->assertFalse(parse_regex('/whatever'));

    }

    function testNormalizeSql() {

        $this->assertEquals(
            "SELECT * FROM `foo` WHERE id = '42' AND name = 'whatever'",
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

    function testDashed() {

        $tests = array(

            '' => '',
            'foo' => 'foo',
            'FooBar' => 'foo-bar',
            'fooBar' => 'foo-bar',
            'Foo--bar' => 'foo-bar',
            'foo-BAR' => 'foo-bar',
            'FOOBAR' => 'foobar',
            'add-slash-test' => 'add-slash-test'

        );

        foreach($tests as $input => $expected) {
            $this->assertEquals($expected, dashed($input), "Failed on '$input'");
        }

    }


    function testUnderscore() {

        $tests = array(

            '' => '',
            'foo' => 'foo',
            'FooBar' => 'foo_bar',
            'fooBar' => 'foo_bar',
            'Foo__bar' => 'foo_bar',
            'foo-BAR' => 'foo_bar',
            'FOOBAR' => 'foobar',
            'add-slash-test' => 'add_slash_test'

        );

        foreach($tests as $input => $expected) {
            $this->assertEquals($expected, underscore($input), "Failed on '$input'");
        }

        $ar = array('add-slash-test');
        $this->assertEquals(
            array('add_slash_test'),
            array_map('underscore', $ar)
        );

        $this->assertEquals('foo/bar', underscore('fooBar', '/'));

    }


}

?>
