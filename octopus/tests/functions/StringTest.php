<?php

    /**
     * @group core
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

    }

?>
