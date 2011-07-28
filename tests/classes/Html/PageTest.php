<?php

Octopus::loadClass('Octopus_Html_Page');

class PageTest extends Octopus_Html_TestCase {

    function testSetTitle() {

        $page = new Octopus_Html_Page();

        $this->assertEquals('', $page->getTitle());
        $page->setTitle('Test Page');
        $this->assertEquals('Test Page', $page->getTitle());
        $this->assertEquals('Test Page', $page->getFullTitle());

        $page->setFullTitle('Test Full Title');
        $this->assertEquals('Test Page', $page->getTitle());
        $this->assertEquals('Test Full Title', $page->getFullTitle());
    }

    function testBreadcrumbs() {

        $page = new Octopus_Html_Page(array(
            'URL_BASE' => '/subdir/'
        ));

        $page->setTitle('Test Page');
        $page->addBreadcrumb('/foo', 'Foo');

        $this->assertEquals('Test Page | Foo', $page->getFullTitle());
        $this->assertEquals(
            array(
                '/subdir/foo' => 'Foo',
            ),
            $page->getBreadcrumbs()
        );

        $page->addBreadcrumb('/foo/bar', 'Bar');
        $this->assertEquals('Test Page | Bar | Foo', $page->getFullTitle());
        $this->assertEquals(
            array(
                '/subdir/foo' => 'Foo',
                '/subdir/foo/bar' => 'Bar'
            ),
            $page->getBreadcrumbs()
        );

        $page->setTitleSeparator(' *** ');
        $this->assertEquals(' *** ', $page->getTitleSeparator());
        $this->assertEquals('Test Page *** Bar *** Foo', $page->getFullTitle());

        $page->setFullTitle('New Title');
        $this->assertEquals('New Title', $page->getFullTitle());

    }

    function testJavascriptVars() {

        $page = new Octopus_Html_Page();

        $page->setJavascriptVar('foo', 'bar');
        $this->assertEquals('bar', $page->getJavascriptVar('foo'));
        $this->assertEquals(
            array(
                'foo' => 'bar'
            ),
            $page->getJavascriptVars()
        );

        $page->setJavascriptVar('higher_priority', 'test', 100);
        $this->assertEquals(
            array(
                'higher_priority' => 'test',
                'foo' => 'bar'
            ),
            $page->getJavascriptVars()
        );

        $this->assertHtmlEquals(
            <<<END
<script type="text/javascript">
    var higher_priority = "test";
    var foo = "bar";
</script>
END
            ,
            trim($page->renderJavascriptVars(true))
        );

    }

    function testCssPathTranslation() {

        $page = new Octopus_Html_Page(array(
            'URL_BASE' => '/subdir/'
        ));

        $files = array(
            'http://foo.bar/test.css',
            'relative/css/file.css',
            '/absolute/css/file.css' => '/subdir/absolute/css/file.css',
            '/subdir/url_base/already/added.css'
        );

        $soFar = array();
        $html = '';

        foreach($files as $key => $value) {

            $toAdd = is_numeric($key) ? $value : $key;
            $expected = $value;

            $page->addCss($toAdd);
            $soFar[$expected] = array(
                'url' => $expected,
                'attributes' => array('media' => 'all'),
                'priority' => 0
            );

            $html .= <<<END
<link href="$expected" rel="stylesheet" type="text/css" media="all" />
END;

            $this->assertEquals(
                $soFar,
                $page->getCssFiles()
            );
        }

        $this->assertHtmlEquals($html, $page->renderCss(true));
    }

    function testCssMedia() {

        $page = new Octopus_Html_Page();
        $page->addCss('foo.css', 'screen');
        $this->assertEquals(
            array(
                'url' => 'foo.css',
                'attributes' => array('media' => 'screen'),
                'priority' => 0
            ),
            $page->getCssFile('foo.css')
        );

        $page = new Octopus_Html_Page();
        $page->addCss('foo.css', array('media' => 'screen'));
        $this->assertEquals(
            array(
                'url' => 'foo.css',
                'attributes' => array('media' => 'screen'),
                'priority' => 0
            ),
            $page->getCssFile('foo.css')
        );

    }

    function testCssPriority() {

        $page = new Octopus_Html_Page();
        $page->addCss('high_priority.css', 100);
        $page->addCss('low_priority.css');

        $this->assertHtmlEquals(
            <<<END
<link href="low_priority.css" rel="stylesheet" type="text/css" media="all" />
<link href="high_priority.css" rel="stylesheet" type="text/css" media="all" />
END
            ,
            $page->renderCss(true)
        );

    }

    function testLiteralCss() {

        $css = <<<END
.myrule {
    font-weight: bold;
}
END;
        $inTag = <<<END
<style type="text/css">
$css
</style>
END;

        $inTagWithComment = <<<END
<style type="text/css">
<!--
$css
-->
</style>
END;


        $page = new Octopus_Html_Page();
        $page->addLiteralCss($css);
        $this->assertHtmlEquals($inTag, $page->renderCss(true));

        $page = new Octopus_Html_Page();
        $page->addLiteralCss($inTag);
        $this->assertHtmlEquals($inTag, $page->renderCss(true));

        $page = new Octopus_Html_Page();
        $page->addLiteralCss($inTagWithComment);
        $this->assertHtmlEquals($inTag, $page->renderCss(true));

    }

    function testJavascript() {

        $tests = array(
            'http://external.com/file.js',
            'relative/file.js',
            '/absolute/path/file.js' => '/subdir/absolute/path/file.js',
        );

        $page = new Octopus_Html_Page(array(
            'URL_BASE' => '/subdir/'
        ));
        $soFar = array();
        $html = '';

        foreach($tests as $key => $value) {

            $toAdd = is_numeric($key) ? $value : $key;
            $expected = $value;

            $page->addJavascript($toAdd);
            $soFar[$expected] = array(
                'url' => $expected,
                'attributes' => array(),
                'priority' => 0
            );

            $this->assertEquals(
                $soFar,
                $page->getJavascriptFiles()
            );

            $html .= <<<END
<script type="text/javascript" src="$expected"></script>
END;

        }

        $this->assertHtmlEquals(
            $html,
            $page->renderJavascript(true)
        );
    }

    function testLiteralJavascript() {

        $script = <<<END
function myfunc() {
}
END;

        $inTag = <<<END
<script type="text/javascript">
$script
</script>
END;

        $inTagWithComment = <<<END
<script type="text/javascript">
<!--
$script
-->
</script>
END;

        $page = new Octopus_Html_Page();
        $page->addLiteralJavascript($inTag);
        $this->assertHtmlEquals($inTag, $page->renderJavascript(true));

        $page = new Octopus_Html_Page();
        $page->addLiteralJavascript($script);
        $this->assertHtmlEquals($inTag, $page->renderJavascript(true));

        $page = new Octopus_Html_Page();
        $page->addLiteralJavascript($inTagWithComment);
        $this->assertHtmlEquals($inTag, $page->renderJavascript(true));
    }

    function testJavascriptPriority() {

        $page = new Octopus_Html_Page();
        $page->addJavascript('low_priority.js');
        $page->addJavascript('high_priority.js', 100);

        $this->assertEquals(
            array(
                'high_priority.js' => array(
                    'url' => 'high_priority.js',
                    'attributes' => array(),
                    'priority' => 100
                ),
                'low_priority.js' => array(
                    'url' => 'low_priority.js',
                    'attributes' => array(),
                    'priority' => 0
                )
            ),
            $page->getJavascriptFiles()
        );
    }

    function testMeta() {

        $page = new Octopus_Html_Page();
        $page->removeMeta('Content-type');

        $page->setMeta('keywords', 'viagra seo google yahoo');
        $this->assertEquals('viagra seo google yahoo', $page->getMeta('keywords'));

        $this->assertHtmlEquals(
            <<<END
<meta name="keywords" content="viagra seo google yahoo" />
END
            ,
            $page->renderMeta(true)
        );

    }

    function testMetaDetectHttpEquiv() {

        $tests = array(
            'Cache-Control' => 'http-equiv',
            'Content-Language' => 'http-equiv',
            'Content-Location' => 'http-equiv',
            'Content-Type' => 'http-equiv',
            'Expires' => 'http-equiv',
            'Last-Modified' => 'http-equiv',
            'Pragma' => 'http-equiv',
            'Refresh' => 'http-equiv',
            'robots' => 'name'
        );

        foreach($tests as $header => $attr) {

            $headers = array($header, strtolower($header), strtoupper($header));

            foreach($headers as $h) {

                $page = new Octopus_Html_Page();
                $page->removeMeta('Content-type');

                $page->setMeta($h, 'foo');

                $this->assertHtmlEquals(
                    <<<END
    <meta $attr="$h" content="foo" />
END
                    ,
                    $page->renderMeta(true),
                    $h
                );
            }

        }

    }

    function testMetaContentType() {

        $page = new Octopus_Html_Page();

        $this->assertEquals('text/html', $page->getContentType());
        $this->assertEquals('UTF-8', $page->getCharset());
        $this->assertHtmlEquals(
            '<meta http-equiv="Content-type" content="text/html; charset=UTF-8" />',
            $page->renderMeta(true)
        );


        $page->setContentType('text/plain');
        $this->assertEquals('text/plain', $page->getContentType());
        $this->assertHtmlEquals(
            '<meta http-equiv="Content-type" content="text/plain; charset=UTF-8" />',
            $page->renderMeta(true)
        );

        $page->setCharset('magic');
        $this->assertEquals('magic', $page->getCharset());

        $this->assertHtmlEquals(
            '<meta http-equiv="Content-type" content="text/plain; charset=magic" />',
            $page->renderMeta(true)
        );
    }

    function testMetaCaching() {

        $page = new Octopus_Html_Page();
        $page->removeMeta('Content-type');

        $this->assertFalse($page->getExpiryDate(), 'expiry date is false by default');
        $this->assertFalse($page->isExpired(), 'page is not expired by default');

        $date = add_days(time(), 3);

        $page->setExpiryDate(date('Y-m-d', $date));
        $this->assertFalse($page->isExpired());
        $this->assertEquals($date, $page->getExpiryDate(), 'getExpiryDate');

        $date = date('r', $date);

        $this->assertHtmlEquals(
            <<<END
<meta http-equiv="Expires" content="$date" />
END
            ,
            $page->renderMeta(true)
        );

        $beginningOfTime = date('r', 0);

        $page->setExpired(true);
        $this->assertTrue($page->isExpired(), 'page is expired after calling setExpired(true)');
        $this->assertHtmlEquals(
            <<<END
<meta http-equiv="Expires" content="$beginningOfTime" />
<meta http-equiv="Cache-control" content="no-cache" />
<meta http-equiv="Pragma" content="no-cache" />
END
            ,
            $page->renderMeta(true)
        );

        $date = add_days(time(), -3);
        $page->setExpiryDate($date);
        $this->assertTrue($page->isExpired());

        $date = date('r', $date);
        $this->assertHtmlEquals(
            <<<END
<meta http-equiv="Expires" content="$date" />
END
            ,
            $page->renderMeta(true)
        );

    }

    function testCanonicalUrl() {

        $page = new Octopus_Html_Page(array(
                'HTTPS' => 'on',
                'HTTP_HOST' => 'foo.bar',
                'URL_BASE' => '/subdir/'
        ));

        $this->assertEquals('', $page->renderLinks(true));

        $tests = array(
            'foo' => 'foo',
            '/foo' => '/subdir/foo',
            'http://whatever.com' => 'http://whatever.com'
        );
        foreach($tests as $input => $expected) {

            $page->setCanonicalUrl($input);
            $this->assertHtmlEquals(
                <<<END
<link href="$expected" rel="canonical" />
END
                ,
                $page->renderLinks(true),
                $input
            );

        }

    }

    function testMetaConvenienceMethods() {

        $fields = array(
            'description',
            'keywords',
            'author',
            'copyright',
            'contact',
            'robots'
        );

        foreach($fields as $f) {

            $setter = 'set' . camel_case($f, true);
            $getter = 'get' . camel_case($f, true);
            $value = "Test $f";

            $page = new Octopus_Html_Page();
            $page->removeMeta('Content-type');


            $page->$setter($value);
            $this->assertEquals($value, $page->$getter());

            $this->assertHtmlEquals(
                <<<END
<meta name="$f" content="$value" />
END
                ,
                $page->renderMeta(true)
            );

        }

    }

    function testImageToolbar() {

        $page = new Octopus_Html_Page();
        $page->removeMeta('Content-type');
        $this->assertEquals('', $page->renderMeta(true));

        $page->setImageToolbarVisible(false);
        $this->assertHtmlEquals(
            <<<END
<meta http-equiv="imagetoolbar" content="no" />
END
            ,
            $page->renderMeta(true)
        );

        $page->setImageToolbarVisible(true);
        $this->assertHtmlEquals('', $page->renderMeta(true));

    }

    function testFavicon() {

        $tests = array(
            'whatever.ico' => array('href' => 'whatever.ico', 'type' => 'image/vnd.microsoft.icon'),
            '/whatever.png' => array('href' => '/subdir/whatever.png', 'type' => 'image/png'),
            '/subdir/whatever.gif' => array('href' => '/subdir/whatever.gif', 'type' => 'image/gif'),
            '/subdir/whatever.jpeg' => array('href' => '/subdir/whatever.jpeg', 'type' => 'image/jpeg')
        );

        foreach($tests as $input => $expected) {

            $page = new Octopus_Html_Page(array(
                'URL_BASE' => '/subdir/'
            ));

            $page->setFavicon($input);
            $this->assertEquals($expected['href'], $page->getFavicon(), $input);

            $this->assertHtmlEquals(
                <<<END
<link href="{$expected['href']}" rel="shortcut icon" type="{$expected['type']}" />
END
                ,
                $page->renderLinks(true),
                $input
            );

        }

    }

    function testInternetExplorerCss() {

        $tests = array(
            array(
                'input' => array('ie' => '<= 6'),
                'expected' => 'lte IE 6'
            ),
            array(
                'input' => array('ie' => '< 6'),
                'expected' => 'lt IE 6'
            ),
            array(
                'input' => array('ie' => 6),
                'expected' => 'IE 6'
            ),

        );

        foreach($tests as $t) {

            $page = new Octopus_Html_Page();
            $page->addCss('ie.css', $t['input']);

            $this->assertHtmlEquals(
            <<<END
<!--[if {$t['expected']}]>
<link href="ie.css" rel="stylesheet" type="text/css" media="all" />
<![endif]-->
END
            ,
                $page->renderCss(true)
            );
        }
    }

    function testAddLink() {

        $tests = array(
            'whatever' => 'whatever',
            '/whatever' => '/subdir/whatever',
            'http://whatever' => 'http://whatever'
        );

        foreach($tests as $input => $expected) {

            $page = new Octopus_Html_Page(array(
                'URL_BASE' => '/subdir/'
            ));

            $this->assertFalse($page->getLink('next'), 'getLink for missing link returns false');

            $page->addLink('next', $input);
            $this->assertHtmlEquals(
                <<<END
<link href="$expected" rel="next" />
END
                ,
                $page->renderLinks(true)
            );

            $this->assertEquals(
                array(
                    'url' => $expected,
                    'rel' => 'next',
                    'type' => null,
                    'attributes' => array(),
                    'priority' => 0
                ),
                $page->getLink('next')
            );

            $page = new Octopus_Html_Page(array('URL_BASE' => '/subdir/'));
            $page->addLink('next', $input, 'text/plain');
            $this->assertHtmlEquals(
                <<<END
<link href="$expected" rel="next" type="text/plain" />
END
                ,
                $page->renderLinks(true)
            );

            $this->assertEquals(
                array(
                    'url' => $expected,
                    'rel' => 'next',
                    'type' => 'text/plain',
                    'attributes' => array(),
                    'priority' => 0
                ),
                $page->getLink('next')
            );


        }
    }

}

?>
