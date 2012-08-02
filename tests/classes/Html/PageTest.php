<?php

class PageTest extends Octopus_App_TestCase {

	function testHeadSectionContent() {

		$page = new Octopus_Html_Page();
		$page->setTitle('foo');
		$page->addCss('/foo.css');
		$page->addJavascript('/foo.js');
		$page->addLink('feed', '/foo.rss', 'text/rss');
		$page->setDescription("foo bar baz bat");

		$this->assertHtmlEquals(
			<<<END
<title>foo</title>
<meta http-equiv="Content-type" content="text/html; charset=UTF-8" />
<meta name="description" content="foo bar baz bat" />
<link href="/foo.css" rel="stylesheet" type="text/css" media="all" />
<script type="text/javascript" src="/foo.js"></script>
<link href="/foo.rss" rel="feed" type="text/rss" />
END
			,
			$page->head->content
		);

	}

    function testCssHasMtime() {

        recursive_touch($this->getSiteDir() . 'css/test.css');
        $mtime = filemtime($this->getSiteDir() . 'css/test.css');

        $page = new Octopus_Html_Page();
        $page->addCss('/css/test.css');

        $this->assertHtmlEquals(
            <<<END
<link href="/site/css/test.css?$mtime" rel="stylesheet" type="text/css" media="all" />
END
            ,
            $page->renderCss(true)
        );


    }

    function testJavascriptHasMtime() {

        recursive_touch($this->getSiteDir() . 'script/test.js');
        $mtime = filemtime($this->getSiteDir() . 'script/test.js');

        $page = new Octopus_Html_Page();
        $page->addJavascript('/script/test.js');

        $this->assertHtmlEquals(
            <<<END
<script type="text/javascript" src="/site/script/test.js?$mtime"></script>
END
            ,
            $page->renderJavascript(true)
        );


    }


    function testNoScriptWeightDoesNotOverrideExisting() {

        $page = new Octopus_Html_Page();
        $page->addJavascript('/some/script.js', 200);
        $page->addJavascript('/some/script.js');

        $this->assertEquals(
            array(
                array(
                    'file' => '/some/script.js',
                    'attributes' => array(),
                    'weight' => 200
            )),
            $this->unsetIndexes($page->getJavascriptFiles())
        );

    }

    function testAddScriptOverridesExisting() {

        $page = new Octopus_Html_Page();
        $page->addJavascript('/some/script.js');
        $page->addJavascript('/some/script.js', 200);

        $this->assertEquals(
            array(
                array(
                    'file' => '/some/script.js',
                    'attributes' => array(),
                    'weight' => 200
            )),
            $this->unsetIndexes($page->getJavascriptFiles())
        );

    }

    function testTitleHtmlEscaped() {

        $page = new Octopus_Html_Page();
        $page->setTitle('< this should be escaped & stuff >');

        $this->assertHtmlEquals(
            <<<END
<title>&lt; this should be escaped &amp; stuff &gt;</title>
END
            ,
            $page->renderTitle(true)
        );

    }

    function testFullTitleHtmlEscaped() {

        $page = new Octopus_Html_Page();
        $page->setFullTitle('< this should be escaped & stuff >');

        $this->assertHtmlEquals(
            <<<END
<title>&lt; this should be escaped &amp; stuff &gt;</title>
END
            ,
            $page->renderTitle(true)
        );

    }


    function testBreadcrumbTitleStuffHtmlEscaped() {

        $page = new Octopus_Html_Page();
        $page->addBreadcrumb('/', '<Home>');
        $page->setTitle('<Title>');

        $this->assertHtmlEquals(
            <<<END
<title>&lt;Title&gt; | &lt;Home&gt;</title>
END
            ,
            $page->renderTitle(true)
        );

    }

    function testSetTitle() {

        $page = new Octopus_Html_Page();

        $this->assertEquals('', $page->getTitle());
        $page->setTitle('dontTest Page');
        $this->assertEquals('dontTest Page', $page->getTitle());
        $this->assertEquals('dontTest Page', $page->getFullTitle());

        $page->setFullTitle('dontTest Full Title');
        $this->assertEquals('dontTest Page', $page->getTitle());
        $this->assertEquals('dontTest Full Title', $page->getFullTitle());
    }

    function testBreadcrumbs() {

        $page = new Octopus_Html_Page(array(
            'URL_BASE' => '/subdir/'
        ));

        $page->setTitle('dontTest Page');
        $page->addBreadcrumb('/foo', 'Foo');

        $this->assertEquals('dontTest Page | Foo', $page->getFullTitle());
        $this->assertEquals(
            array(
                '/subdir/foo' => 'Foo',
            ),
            $page->getBreadcrumbs()
        );

        $page->addBreadcrumb('/foo/bar', 'Bar');
        $this->assertEquals('dontTest Page | Bar | Foo', $page->getFullTitle());
        $this->assertEquals(
            array(
                '/subdir/foo' => 'Foo',
                '/subdir/foo/bar' => 'Bar'
            ),
            $page->getBreadcrumbs()
        );

        $page->setTitleSeparator(' *** ');
        $this->assertEquals(' *** ', $page->getTitleSeparator());
        $this->assertEquals('dontTest Page *** Bar *** Foo', $page->getFullTitle());

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

        $page->setJavascriptVar('lower_weight', 'dontTest', -100);
        $this->assertEquals(
            array(
                'lower_weight' => 'dontTest',
                'foo' => 'bar'
            ),
            $page->getJavascriptVars()
        );

        $this->assertHtmlEquals(
            <<<END
<script type="text/javascript">
    var lower_weight = "dontTest";
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
            'http://foo.bar/dontTest.css',
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
            $soFar[] = array(
                'file' => $expected,
                'attributes' => array('media' => 'all'),
                'weight' => 0
            );

            $html .= <<<END
<link href="$expected" rel="stylesheet" type="text/css" media="all" />
END;

            $this->assertEquals(
                $soFar,
                $this->unsetIndexes($page->getCssFiles())
            );
        }

        $this->assertHtmlEquals($html, $page->renderCss(true));
    }

    function testCssMedia() {

        $page = new Octopus_Html_Page();
        $page->addCss('foo.css', 'screen');
        $this->assertEquals(
        	array(
	            array(
	                'file' => 'foo.css',
	                'attributes' => array('media' => 'screen'),
	                'weight' => 0
	            )
	        ),
            $this->unsetIndexes($page->getCssFiles())
        );

        $page = new Octopus_Html_Page();
        $page->addCss('foo.css', array('media' => 'screen'));
        $this->assertEquals(
        	array(
	            array(
	                'file' => 'foo.css',
	                'attributes' => array('media' => 'screen'),
	                'weight' => 0
	            ),
	        ),
            $this->unsetIndexes($page->getCssFiles())
        );

    }

    function testCssWeight() {

        $page = new Octopus_Html_Page();
        $page->addCss('high_weight.css');
        $page->addCss('low_weight.css', -100);

        $this->assertHtmlEquals(
            <<<END
<link href="low_weight.css" rel="stylesheet" type="text/css" media="all" />
<link href="high_weight.css" rel="stylesheet" type="text/css" media="all" />
END
            ,
            $page->renderCss(true)
        );

    }


    function testExternalJavascript() {

        $page = new Octopus_Html_Page();
        $page->addJavascript('http://example.com/file.js');
        $this->assertHtmlEquals(
            <<<END
<script type="text/javascript" src="http://example.com/file.js"></script>
END
            ,
            $page->renderJavascript(true)
        );

    }

    function testAbsoluteJavascriptInSiteDir() {

        $file = 'test.js';
        file_put_contents($this->getSiteDir() . $file, '/* test */');
        $mtime = filemtime($this->getSiteDir() . $file);

        $page = new Octopus_Html_Page(array('URL_BASE' => '/subdir/'));
        $page->addJavascript('/test.js');
        $this->assertHtmlEquals(
            <<<END
<script type="text/javascript" src="/subdir/site/test.js?$mtime"></script>
END
            ,
            $page->renderJavascript(true)
        );

    }

    function testPhysicalPathJavascript() {

        $file = $this->getRootDir() . 'test.js';
        file_put_contents($file, '/* test */');
        $mtime = filemtime($file);

        $page = new Octopus_Html_Page(array('URL_BASE' => '/subdir/'));
        $page->addJavascript($file);

        $this->assertHtmlEquals(
            <<<END
<script type="text/javascript" src="/subdir/test.js?$mtime"></script>
END
            ,
            $page->renderJavascript(true)
        );

    }

    function testRelativeJavascript() {

        $page = new Octopus_Html_Page(array('URL_BASE' => '/subdir/'));
        $page->addJavascript('js/relative.js');

        $this->assertHtmlEquals(
            <<<END
<script type="text/javascript" src="js/relative.js"></script>
END
            ,
            $page->renderJavascript(true)
        );

    }

    function testMissingJavascript() {

        $page = new Octopus_Html_Page(array('URL_BASE' => '/subdir/'));
        $page->addJavascript('/missing.js');

        $this->assertHtmlEquals(
            <<<END
<script type="text/javascript" src="/subdir/missing.js"></script>
END
            ,
            $page->renderJavascript(true)
        );

    }


    function testJavascriptWeight() {

        $page = new Octopus_Html_Page();
        $page->addJavascript('high_weight.js');
        $page->addJavascript('low_weight.js', -100);

        $this->assertHtmlEquals(
            <<<END
<script type="text/javascript" src="low_weight.js"></script>
<script type="text/javascript" src="high_weight.js"></script>
END
            ,
            $page->renderJavascript(true)
        );
    }

    function unsetIndexes($ar) {
        foreach($ar as $key => &$value) {
            unset($ar[$key]['index']);
        }
        return $ar;
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

            $this->assertEquals(array(), $page->getLinks(), 'no links by default');

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
	                array(
	                    'url' => $expected,
	                    'rel' => 'next',
	                    'type' => null,
	                    'attributes' => array(),
	                    'weight' => 0
	                ),
	            ),
                $page->getLinks()
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
	                array(
	                    'url' => $expected,
	                    'rel' => 'next',
	                    'type' => 'text/plain',
	                    'attributes' => array(),
	                    'weight' => 0
	                ),
	            ),
                $page->getLinks()
            );


        }
    }

    function testUseJavascriptAliases() {

        $page = new Octopus_Html_Page(array(
            'URL_BASE' => '/subdir/'
        ));

        $dir = $this->getSiteDir() . 'script/';
        mkdir($dir);
        touch($dir . 'global.js');

        $page->addJavascriptAlias(
            array(
                'http://jquery.com/jquery.js',
                '/script/global.js'
            ),
            '/jquery_and_global.js'
        );

        $page->addJavascript('/script/global.js');
        $page->addJavascript('/script/this_page.js');
        $page->addJavascript('http://jquery.com/jquery.js', -100);


        $this->assertHtmlEquals(
            <<<END
<script type="text/javascript" src="/subdir/jquery_and_global.js"></script>
<script type="text/javascript" src="/subdir/script/this_page.js"></script>
END
            ,
            $page->renderJavascript(true)
        );

    }

    function testJavascriptAliasesUseBest() {

        $page = new Octopus_Html_Page(array('URL_BASE' => '/subdir/'));

        touch($this->getSiteDir() . 'a.js');
        touch($this->getSiteDir() . 'b.js');
        touch($this->getSiteDir() . 'c.js');
        touch($this->getSiteDir() . 'd.js');

        $page->addJavascript('/a.js');
        $page->addJavascript('/b.js');
        $page->addJavascript('/c.js');
        $page->addJavascript('/d.js');

        $page->addJavascriptAlias(array('/a.js', '/c.js'), '/ac.js');
        $page->addJavascriptAlias(array('/a.js', '/b.js', '/d.js'), '/abd.js');

        $cmtime = filemtime($this->getSiteDir() . 'c.js');

        $this->assertHtmlEquals(
            <<<END
<script type="text/javascript" src="/subdir/abd.js"></script>
<script type="text/javascript" src="/subdir/site/c.js?$cmtime"></script>
END
            ,
            $page->renderJavascript(true)
        );

    }

    function testJavascriptAliasesUseLowestWeight() {

        $page = new Octopus_Html_Page();

        $page->addJavascript('/a.js');
        $page->addJavascript('/b.js');
        $page->addJavascript('/c.js', -1000);

        touch($this->getRootDir() . 'a.js');
        touch($this->getRootDir() . 'b.js');
        touch($this->getRootDir() . 'c.js');

        $page->addJavascriptAlias(array('/c.js', '/b.js'), '/cb.js');

        $amtime = filemtime($this->getRootDir() . 'a.js');

        $this->assertHtmlEquals(
            <<<END
<script type="text/javascript" src="/cb.js"></script>
<script type="text/javascript" src="/a.js?$amtime"></script>
END
            ,
            $page->renderJavascript(true)
        );

    }

    function testGetJavascriptFilesWithAliases() {

        $page = new Octopus_Html_Page(array('URL_BASE' => '/subdir/'));

        $page->addJavascript('/a.js');
        $page->addJavascript('/b.js');
        $page->addJavascript('/c.js');
        $page->addJavascriptAlias(array('/a.js', '/c.js'), '/ac.js');

        touch($this->getRootDir() . 'a.js');
        touch($this->getRootDir() . 'b.js');
        touch($this->getRootDir() . 'c.js');

        $files = $page->getJavascriptFiles();
        $this->assertEquals(2, count($files), '# of javascript files');

        $f = array_shift($files);
        $this->assertEquals('/subdir/ac.js', $f['file']);

        $f = array_shift($files);
        $bmtime = filemtime($this->getRootDir() . 'b.js');
        $this->assertEquals("/subdir/b.js?$bmtime", $f['file']);

    }

    function testUseCssAliases() {

        $page = new Octopus_Html_Page(array(
            'URL_BASE' => '/subdir/'
        ));

        $dir = $this->getSiteDir() . 'css/';
        mkdir($dir);
        touch($dir . 'styles.css');


        $page->addCssAlias(
            array(
                'http://server.com/base.css',
                '/css/styles.css'
            ),
            '/base_and_styles.css'
        );

        $page->addCss('http://server.com/base.css');
        $page->addCss('/css/styles.css');
        $page->addCss('/something_else.css');

        $this->assertHtmlEquals(
            <<<END
<link href="/subdir/base_and_styles.css" rel="stylesheet" type="text/css" media="all" />
<link href="/subdir/something_else.css" rel="stylesheet" type="text/css" media="all" />
END
            ,
            $page->renderCss(true)
        );

    }

    function testAddJavascriptToDifferentArea() {

        $page = new Octopus_Html_Page();
        $page->addJavascript('/global.js', 'bottom', 100);

        $this->assertEquals(array(), $page->getJavascriptFiles('head'));
        $this->assertEquals(
            array(
                array(
                    'file' => '/global.js',
                    'attributes' => array(),
                    'weight' => 100
                )
            ),
            $this->unsetIndexes($page->getJavascriptFiles('bottom'))
        );

        $this->assertEquals('', trim($page->renderJavascript(true)));

        $this->assertHtmlEquals(
            <<<END
<script type="text/javascript" src="/global.js"></script>
END
            ,
            $page->renderJavascript('bottom', true)
        );

    }

    function testAddJavascriptMagicMethods() {

        $page = new Octopus_Html_Page();
        $page->addBottomJavascript('/global.js', 100);

        $this->assertEquals(array(), $page->getJavascriptFiles());
        $this->assertEquals(
            array(
                array(
                    'file' => '/global.js',
                    'attributes' => array(),
                    'weight' => 100
                )
            ),
            $this->unsetIndexes($page->getJavascriptFiles('bottom'))
        );

        $this->assertEquals('', trim($page->renderJavascript(true)));

        $this->assertHtmlEquals(
            <<<END
<script type="text/javascript" src="/global.js"></script>
END
            ,
            $page->renderJavascript('bottom', true)
        );

    }

    function testRenderHead() {

        $page = new Octopus_Html_Page();

        touch($this->getSiteDir() . '/global.js');
        $jsMtime = filemtime($this->getSiteDir() . '/global.js');

        touch($this->getSiteDir() . '/styles.css');
        $cssMtime = filemtime($this->getSiteDir() . '/styles.css');

        $page->addJavascript('/global.js');
        $page->addCss('/styles.css');
        $page->setJavascriptVar('foo', 'bar');
        $page->setTitle('My Title');

        $this->assertHtmlEquals(
            <<<END
<head>
    <title>My Title</title>
    <meta http-equiv="Content-type" content="text/html; charset=UTF-8" />
    <link href="/site/styles.css?$cssMtime" rel="stylesheet" type="text/css" media="all" />
    <script type="text/javascript">
        var foo = "bar";
    </script>
    <script type="text/javascript" src="/site/global.js?$jsMtime"></script>
</head>
END
            ,
            $page->renderHead(true)
        );
    }

    /**
     * @group slow
     */
    function testCombineJavascript() {

        $page = new Octopus_Html_Page(array('URL_BASE' => '/subdir/'));

        $siteDir = $this->getSiteDir();
        $scriptDir = $siteDir . 'script/';
        mkdir($scriptDir);

        file_put_contents(
            "{$scriptDir}a.js",
            <<<END
/* contents of file a */
END
        );

        file_put_contents(
            "{$scriptDir}b.js",
            <<<END
/* contents of file b */
END
        );

        $page->addJavascript('/script/a.js');
        $page->addJavascript('/script/b.js');
        $page->setJavascriptMinifier('combine');

        $js = $page->getJavascriptFiles();
        $this->assertEquals(1, count($js));
        $js = array_shift($js);
        $file = $this->getRootDir() . preg_replace('#^/subdir/#i', '', $js['file']);
        $file = preg_replace('/\?.*/', '', $file);

        $this->assertEquals(
            <<<END
/* contents of file a */

/* contents of file b */
END
            ,
            file_get_contents($file)
        );

        sleep(2);

        file_put_contents(
            "{$scriptDir}a.js",
            <<<END
/* contents of file a UPDATED!!!! */
END
        );

        $js = $page->getJavascriptFiles();
        $this->assertEquals(1, count($js));
        $js = array_shift($js);
        $file = $this->getRootDir() . preg_replace('#^/subdir/#i', '', $js['file']);
        $file = preg_replace('/\?.*/', '', $file);

        $this->assertEquals(
            <<<END
/* contents of file a UPDATED!!!! */

/* contents of file b */
END
            ,
            file_get_contents($file)
        );

        sleep(2);

        file_put_contents(
            "{$scriptDir}b.js",
            <<<END
/* contents of file b UPDATED!!!! */
END
        );

        $js = $page->getJavascriptFiles();
        $this->assertEquals(1, count($js));
        $js = array_shift($js);
        $file = $this->getRootDir() . preg_replace('#^/subdir/#i', '', $js['file']);
        $file = preg_replace('/\?.*/', '', $file);

        $this->assertEquals(
            <<<END
/* contents of file a UPDATED!!!! */

/* contents of file b UPDATED!!!! */
END
            ,
            file_get_contents($file)
        );


    }

    /**
     * @group slow
     */
    function testCombineJavascriptRegenerateOnDemand() {

        $page = new Octopus_Html_Page(array('URL_BASE' => '/subdir/'));

        $siteDir = $this->getSiteDir();
        $scriptDir = $siteDir . 'script/';
        mkdir($scriptDir);

        file_put_contents(
            "{$scriptDir}a.js",
            <<<END
/* contents of file a */
END
        );

        file_put_contents(
            "{$scriptDir}b.js",
            <<<END
/* contents of file b */
END
        );

        $page->addJavascript('/script/a.js');
        $page->addJavascript('/script/b.js');
        $page->setJavascriptMinifier('combine');

        $js = $page->getJavascriptFiles();
        $this->assertEquals(1, count($js));
        $js = array_shift($js);
        $file = $this->getRootDir() . preg_replace('#^(/subdir/|\?.*)#i', '', $js['file']);
        $file = preg_replace('/\?.*/', '', $file);
        $mtime = filemtime($file);

        sleep(2);

        $page = new Octopus_Html_Page(array('URL_BASE' => '/subdir/'));

        $siteDir = $this->getSiteDir();
        $scriptDir = $siteDir . 'script/';

        $page->addJavascript('/script/a.js');
        $page->addJavascript('/script/b.js');
        $page->setJavascriptMinifier('combine');

        $js = $page->getJavascriptFiles();
        $this->assertEquals(1, count($js));
        $js = array_shift($js);
        $file = $this->getRootDir() . preg_replace('#^/subdir/#i', '', $js['file']);
        $file = preg_replace('/\?.*/', '', $file);
        $this->assertEquals($mtime, filemtime($file));

    }

    function testAddJavascriptWackWack() {

    	$page = new Octopus_Html_Page(array('URL_BASE' => '/some/crazy/url/base/'));
    	$page->addJavascript('//some-server.local/file.js');

    	$this->assertHtmlEquals(
    		<<<END
<script type="text/javascript" src="//some-server.local/file.js"></script>
END
			,
			$page->renderJavascript(true)
    	);

    }


    function testAddCssWackWack() {

    	$page = new Octopus_Html_Page(array('URL_BASE' => '/some/crazy/url/base/'));
    	$page->addCss('//some-server.local/file.css');

    	$this->assertHtmlEquals(
    		<<<END
<link href="//some-server.local/file.css" rel="stylesheet" type="text/css" media="all" />
END
			,
			$page->renderCss(true)
    	);

    }

    function testCombinedJavascriptFilesGetWeightOfHeaviestElement() {
    	return $this->markTestSkipped('Is this the best way to do this?');
    	$scriptDir = $this->getRootDir() . 'script/';
    	mkdir($scriptDir);

    	file_put_contents($scriptDir . '/combine1.js', '/* combine 1 */');
    	file_put_contents($scriptDir . '/combine2.js', '/* combine 2 */');
    	file_put_contents($scriptDir . '/combine3.js', '/* combine 3 */');

    	$page = new Octopus_Html_Page();

    	$page->addJavascript('/script/combine1.js', 500);
    	$page->addJavascript('/script/combine2.js', -1000);
    	$page->addJavascript('http://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js', 200);

    	$page->setJavascriptMinifier('combine');

    	$files = $this->unsetIndexes($page->getJavascriptFiles());

    	$this->assertEquals(2, count($files), 'combined into 2 files');

    	$file1 = array_shift($files);
    	$file2 = array_shift($files);

    	$this->assertEquals('http://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js', $file1['file'], '1st file is cdn jquery');
    	$this->assertEquals(200, $file1['weight'], 'cdn jquery keeps weight');

    	$contents = file_get_contents(rtrim($this->getRootDir(), '/') . preg_replace('/\?.*/', '', $file2['file']));

    	$this->assertEquals(
    		<<<END
/* combine 2 */

/* combine 1 */
END
			,
			$contents,
			'weights are preserved in combined file'
    	);

    	$this->assertEquals(500, $file2['weight'], 'first file gets heaviest combined weight');

    }

    function testOnlyJavascriptVarsNoFiles() {

    	$page = new Octopus_Html_Page();
    	$page->setJavascriptVar('foo', 'bar');

    	$this->assertHtmlEquals(
    		<<<END
<head>
<title></title>
<meta http-equiv="Content-type" content="text/html; charset=UTF-8" />
<script type="text/javascript">
var foo = "bar";
</script>
</head>
END
			,
			$page->renderHead(true)
    	);

    }

    function testMetaConvenienceMethods() {

        $fields = array(
            'description',
            'keywords',
        );

        foreach($fields as $f) {

            $setter = 'set' . camel_case($f, true);
            $getter = 'get' . camel_case($f, true);
            $value = "dontTest $f";

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

    function testSectionsAvailableAsProperties() {

    	$page = new Octopus_Html_Page();
    	$page->head->addJavascript('/foo.js');
    	$page->foot->addJavascript('/bar.js');

    	$this->assertSame($page->getSection('head'), $page->head);
    	$this->assertSame($page->getSection('foot'), $page->foot);

    	$this->assertHtmlEquals('<script type="text/javascript" src="/foo.js"></script>', $page->head->renderJavascript(true));
    	$this->assertHtmlEquals('<script type="text/javascript" src="/bar.js"></script>', $page->foot->renderJavascript(true));

    }

	function testSectionsAvailableViaArrayAccess() {

    	$page = new Octopus_Html_Page();
    	$page['head']->addJavascript('/foo.js');
    	$page['foot']->addJavascript('/bar.js');

    	$this->assertSame($page->getSection('head'), $page['head']);
    	$this->assertSame($page->getSection('foot'), $page['foot']);

    	$this->assertHtmlEquals('<script type="text/javascript" src="/foo.js"></script>', $page->head->renderJavascript(true));
    	$this->assertHtmlEquals('<script type="text/javascript" src="/bar.js"></script>', $page->foot->renderJavascript(true));

    }

    function testHeadSectionRendersWithHeadTag() {

    	$page = new Octopus_Html_Page();
    	$page->setTitle('Foo title');
    	$page->addCss('/foo.css');
    	$page->addJavascript('/foo.js');
    	$page->setJavascriptVar('foo', 'bar');

    	$this->assertHtmlEquals(
    		<<<END
<head>
<title>Foo title</title>
<meta http-equiv="Content-type" content="text/html; charset=UTF-8" />
<link href="/foo.css" rel="stylesheet" type="text/css" media="all" />
<script type="text/javascript">
var foo = "bar";
</script>
<script type="text/javascript" src="/foo.js"></script>
</head>
END
			,
			(string)$page['head']
    	);

    }

    function testFootSectionRenderWithoutTag() {

    	$page = new Octopus_Html_Page();
    	$page->foot->addJavascript('/foo.js');

    	$this->assertHtmlEquals(
    		<<<END
<script type="text/javascript" src="/foo.js"></script>
END
			,
			(string)$page['foot']
    	);

    }

    function testSectionJavascriptHtmlViaArrayAccess() {

    	$page = new Octopus_Html_Page();
    	$page->head->addJavascript('/foo.js');
    	$this->assertEquals($page->head->renderJavascript(true), $page->head['scripts']);

    }

    function testSectionCssHtmlViaArrayAccess() {

    	$page = new Octopus_Html_Page();
    	$page->head->addJavascript('/foo.css');
    	$this->assertEquals($page->head->renderCss(true), $page->head['css']);

    }

    function testHeadSectionJavascriptIncludesVars() {

    	$page = new Octopus_Html_Page();
    	$page->setJavascriptVar('foo', 'bar');
    	$page->head->addJavascript('/foo.js');

    	$this->assertHtmlEquals(
    		<<<END
<script type="text/javascript">
var foo = "bar";
</script>
<script type="text/javascript" src="/foo.js"></script>
END
			,
			$page->head->scripts
    	);

    }



}
