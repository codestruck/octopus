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

        function testIsRegex() {

            $this->assertTrue(is_regex('/whatever/i', $flags));
            $this->assertFalse(is_regex('/whatever', $flags));

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

    }

?>
