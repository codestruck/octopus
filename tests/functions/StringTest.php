<?php

/**
 * @group core
 * @group string
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class StringTests extends PHPUnit_Framework_TestCase {

    /**
     * @dataProvider getFormatMoneyTests
     */
    function testFormatMoney($input, $expected) {

        $this->assertEquals($expected, format_money($input));

    }

    function getFormatMoneyTests() {

        return array(

            array('', ''),
            array('0', '$0.00'),
            array('1', '$1.00'),
            array('2.4', '$2.40'),
            array('1234.56', '$1,234.56'),
            array('.99999', '$0.99'),
            array('not a number', 'not a number'),
            array(null, ''),

        );

    }

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
            'category' => 'categories',
            'lightbox' => 'lightboxes'
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
            'foo \\\\ bar' => '%foo \\ bar%',
            'f*' => 'f%',
            'f\\*' => '%f*%',
            'f\\?' => '%f?%'
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

    function testHArray() {

        $array = array(
            '<b>' => '<i>test</i>',
            2 => array(
                '<i>' => '<b>test</b>',
                'foo' => true,
                'bar' => false,
                'baz' => null
            )
        );

        $escaped = h($array);

        $this->assertTrue(is_array($escaped), 'h(array) returns an array');

        $this->assertEquals(
            array(
                '&lt;b&gt;' => '&lt;i&gt;test&lt;/i&gt;',
                2 => array(
                    '&lt;i&gt;' => '&lt;b&gt;test&lt;/b&gt;',
                    'foo' => true,
                    'bar' => false,
                    'baz' => null
                )
            ),
            $escaped
        );

        $this->assertTrue($escaped[2]['foo'] === true, 'true preserved');
        $this->assertTrue($escaped[2]['bar'] === false, 'false preserved');
        $this->assertTrue($escaped[2]['baz'] === null, 'null preserved');
    }

    function testHObject() {

        $obj = new HTestObject();
        $obj->value = "<b>hi there!</b>";

        $this->assertEquals('&lt;b&gt;hi there!&lt;/b&gt;', h($obj));

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

    function testToCssClass() {

        $tests = array(
            '' => '',
            'FOO' => 'foo',
            'fooBar' => 'foo-bar',
            'foo_bar' => 'foo-bar',
            '27foo' => '-27-foo',
        );

        foreach($tests as $input => $expected) {
            $this->assertEquals($expected, to_css_class($input), "$input -> $expected");
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
            'fox' => 'foxes',
            'class' => 'classes'

         );

        foreach($tests as $input => $expected) {
            $this->assertEquals(
                $expected,
                pluralize($input),
                'failed on ' . $input
            );
        }
    }

    function testPluralizeWithNumber() {

        $this->assertEquals('comments', pluralize('comment', 2));
        $this->assertEquals('comments', pluralize('comment', 0));
        $this->assertEquals('comment', pluralize('comment', 1));
        $this->assertEquals('comment', pluralize('comment', 1));
        $this->assertEquals('comments', pluralize('comment', -1));
        $this->assertEquals('comment', pluralize('comment', 1.0));


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
            'add-slash-test' => 'add_slash_test',
            'separate26numbers' => 'separate_26_numbers',

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

    function testPluralCount() {

        $one = array('a thing');
        $two = array('one', 'two');
        $five = array('a', 'b', 'c', 'd', 'e');

        $this->assertEquals('1 comment', plural_count($one, 'comment'));
        $this->assertEquals('2 comments', plural_count($two, 'comment'));
        $this->assertEquals('5 comments', plural_count($five, 'comment'));

        $this->assertEquals('1 comment', plural_count(1, 'comment'));
        $this->assertEquals('2 comments', plural_count(2, 'comment'));
        $this->assertEquals('5 comments', plural_count(5, 'comment'));

        $this->assertEquals('1 comment', plural_count('1', 'comment'));
        $this->assertEquals('2 comments', plural_count('2', 'comment'));
        $this->assertEquals('5 comments', plural_count('5', 'comment'));

        $this->assertEquals('0 comments', plural_count(array(), 'comment'));
        $this->assertEquals('0 comments', plural_count(0, 'comment'));
        $this->assertEquals('0 comments', plural_count('0', 'comment'));

    }

    function testLinkify() {

        $text = <<<END
<a href="http://cnn.com/">CNN</a>
<a href="http://cnn.com">http://cnn.com/</a>
http://cnn.com/
(http://cnn.com/)
END;

        $result = <<<END
<a href="http://cnn.com/">CNN</a>
<a href="http://cnn.com">http://cnn.com/</a>
<a href="http://cnn.com/">http://cnn.com/</a>
(<a href="http://cnn.com/">http://cnn.com/</a>)
END;

        $this->assertEquals($result, linkify($text));

        $text = 'http://cnn.com/';
        $result = '<a href="http://cnn.com/">http://cnn.com/</a>';

        $this->assertEquals($result, linkify($text));

    }

    function testLinkifyCasual() {

        $text = <<<END
<a href="http://cnn.com/">CNN</a>
<a href="http://cnn.com">cnn.com/</a>
www.google.com
www.google.com/foo/bar.php?abc=1
facebook.com
facebook.com/profile
(causeway.org)
END;

        $result = <<<END
<a href="http://cnn.com/">CNN</a>
<a href="http://cnn.com">cnn.com/</a>
<a href="http://www.google.com">www.google.com</a>
<a href="http://www.google.com/foo/bar.php?abc=1">www.google.com/foo/bar.php?abc=1</a>
<a href="http://facebook.com">facebook.com</a>
<a href="http://facebook.com/profile">facebook.com/profile</a>
(<a href="http://causeway.org">causeway.org</a>)
END;

        $this->assertEquals($result, linkify($text));

    }

    function testLinkifyNotTooCasual() {

        $text = <<<END
My sentance. Oops.a typo.  google.com
lol...
being ‘closed.’ It
problems...they
END;

        $result = <<<END
My sentance. Oops.a typo.  <a href="http://google.com">google.com</a>
lol...
being ‘closed.’ It
problems...they
END;

        $this->assertEquals($result, linkify($text));

    }

    function testLinkifyExternal() {

        $text = <<<END
<a href="http://cnn.com/">CNN</a>
<a href="http://cnn.com">http://cnn.com/</a>
http://cnn.com/
END;

        $result = <<<END
<a href="http://cnn.com/" rel="nofollow" target="_blank">CNN</a>
<a href="http://cnn.com" rel="nofollow" target="_blank">http://cnn.com/</a>
<a href="http://cnn.com/" rel="nofollow" target="_blank">http://cnn.com/</a>
END;

        $this->assertEquals($result, linkify_external($text));

        $text = 'http://cnn.com/';
        $result = '<a href="http://cnn.com/" rel="nofollow" target="_blank">http://cnn.com/</a>';

        $this->assertEquals($result, linkify_external($text));

    }

    function testLinkifyHtml() {

        $text = <<<END
<br> http://cnn.com/
http://cnn.com/<br>

END;

        $result = <<<END
<br> <a href="http://cnn.com/">http://cnn.com/</a>
<a href="http://cnn.com/">http://cnn.com/</a><br>

END;

        $this->assertEquals($result, linkify($text));

    }

    function testLinkifyDot() {

        $text = <<<END
http://cnn.com/)
http://cnn.com/.
http://cnn.com/).
http://cnn.com/.)
google.com.

END;

        $result = <<<END
<a href="http://cnn.com/">http://cnn.com/</a>)
<a href="http://cnn.com/">http://cnn.com/</a>.
<a href="http://cnn.com/">http://cnn.com/</a>).
<a href="http://cnn.com/">http://cnn.com/</a>.)
<a href="http://google.com">google.com</a>.

END;

        $this->assertEquals($result, linkify($text));

    }

    function testAddLinkReferences() {

        $data = array(
            'one' => 'foo',
            'two' => 'has spaces',
        );

        $str = 'one=foo&amp;two=has%20spaces';

        $text = <<<END
<a href="http://cnn.com/">CNN</a>
<a href="http://cnn.com">http://cnn.com/</a>
<a href="http://www.cnn.com/">CNN</a>
<a href="http://cnn.com/?mike=1">CNN</a>
<a href="http://msnbc.com">http://cnn.com/tricky</a>
END;

        $result = <<<END
<a href="http://cnn.com/?$str">CNN</a>
<a href="http://cnn.com/?$str">http://cnn.com/</a>
<a href="http://www.cnn.com/?$str">CNN</a>
<a href="http://cnn.com/?mike=1&amp;$str">CNN</a>
<a href="http://msnbc.com">http://cnn.com/tricky</a>
END;

        $this->assertEquals($result, add_link_references($text, 'cnn.com', $data));

    }

    function testPrettyJson() {

        $data = array(
            'foo' => 'baz',
            'thing_id' => '1234',
        );

        $this->assertEquals(<<<END
{
    "foo": "baz",
    "thing_id": "1234"
}
END
            , pretty_json_encode($data));

    }

    function testPrettyJsonDeep() {

        $data = array(
            'foo' => 'baz',
            'thing_id' => '1234',
            'items' => array(
                array(
                    'id' => 1,
                    'name' => 'Asdf',
                    'colors' => array(
                        'green',
                        'blue',
                    )
                ),
                array(
                    'id' => 2,
                    'name' => 'Asdf',
                ),
            ),
        );

        $this->assertEquals(<<<END
{
    "foo": "baz",
    "thing_id": "1234",
    "items": [
        {
            "id": 1,
            "name": "Asdf",
            "colors": [
                "green",
                "blue"
            ]
        },
        {
            "id": 2,
            "name": "Asdf"
        }
    ]
}
END
            , pretty_json_encode($data));

    }

    function testPrettyJsonQuotes() {

        $data = array(
            'foo' => 'ba{z',
            'thing_id' => '12:34',
        );

        $this->assertEquals(<<<END
{
    "foo": "ba{z",
    "thing_id": "12:34"
}
END
            , pretty_json_encode($data));

    }

    function testPrettyJsonHtml() {

        $data = array(
            'html' => '<img src="https://s3.amazonaws.com/foo/foo.jpg" alt="foo" />',
        );

        $this->assertEquals(<<<END
{
    "html": "<img src=\"https:\/\/s3.amazonaws.com\/foo\/foo.jpg\" alt=\"foo\" \/>"
}
END
            , pretty_json_encode($data));

    }

    function testRemoveExtension() {
        $this->assertEquals('pic', remove_extension('pic.png'));
        $this->assertEquals('pic.large', remove_extension('pic.large.png'));
        $this->assertEquals('pic', remove_extension('pic'));
        $this->assertEquals('pic 978', remove_extension('pic 978.png'));
        $this->assertEquals('"pic"', remove_extension('"pic".png'));
        $this->assertEquals('pic_name', remove_extension('pic_name.png'));
    }

}

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class HTestObject {

    public $value;

    public function __toString() {
        return $this->value;
    }

}
