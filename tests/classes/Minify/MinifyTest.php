<?php
/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class MinifyTest extends Octopus_App_TestCase {

    function testAliasStrategyLocalFiles() {

        $s = new Octopus_Minify_Strategy_Alias();

        $s->addAlias(array('/a.js', '/b.js'), '/ab.js');

        $minified = $s->minify(array('/a.js', '/b.js', '/c.js'));

        $this->assertEquals(
            array('/ab.js' => array('/a.js', '/b.js')),
            $minified
        );

    }

    function testAliasRemoteFiles() {

        $s = new Octopus_Minify_Strategy_Alias();
        $s->addAlias(array('http://a.com/script.js', 'http://b.com/script.js'), '/ab.js');

        $minified = $s->minify(array('http://a.com/script.js', '/whatever.js', 'http://b.com/script.js'));

        $this->assertEquals(
            array(
                '/ab.js' => array('http://a.com/script.js', 'http://b.com/script.js')
            ),
            $minified
        );

    }

    /**
     * @group slow
     */
    function testSrcStrategy() {

        $app = $this->getApp();

        foreach(array('SITE_DIR', 'ROOT_DIR') as $dirName) {

            $dir = $app->getOption($dirName);
            $file = $dir . 'file.js';
            $src = $dir . 'file_src.js';

            $rootDir = $app->getOption('ROOT_DIR');
            $urlDir = '/' . substr($dir, strlen($rootDir));

            touch($file);
            sleep(1);
            touch($src);

            $strat = new Octopus_Minify_Strategy_Src();

            $this->assertEquals(
                array(
                    $src => array($file)
                ),
                $strat->minify(array($file)),
                "dir: $dirName"
            );

            sleep(1);
            touch($file);

            $this->assertEquals(
                array(
                    $file => array($file)
                ),
                $strat->minify(array($file))
            );

            unlink($file);

            // if only src exists, link to src
            $this->assertEquals(
                array(
                    $src => array($file)
                ),
                $strat->minify(array($file))
            );

            unlink($src);

            $this->assertEquals(array(), $strat->minify(array('/file.js')));
        }

    }

    /**
     * @group slow
     */
    function testCombineStrategy() {

        $app = $this->getApp();

        foreach(array('SITE_DIR', 'ROOT_DIR') as $dirName) {

            $dir = $app->getOption($dirName);
            $cacheDir = $app->getOption('OCTOPUS_CACHE_DIR');

            $a = $dir . 'a.js';
            $b = $dir . 'b.js';

            $aContents = 'contents of file a';
            $bContents = 'contents of file b';

            file_put_contents($a, $aContents);
            file_put_contents($b, $bContents);

            $strat = new Octopus_Minify_Strategy_Combine();

            $deleteHash = md5("|$a|$b");
            $uniqueHash = md5("|$a?" . filemtime($a) . "|$b?" . filemtime($b));
            $cacheFile = $this->getCacheDir() . "combine/$deleteHash-$uniqueHash.js";

            $this->assertEquals(
                array(
                    $cacheFile => array($a, $b)
                ),
                $strat->minify(array($a, $b))
            );

            $this->assertEquals(
                <<<END
$aContents

$bContents
END
                ,
                file_get_contents($cacheFile)
            );

            sleep(1);

            // Modify a file
            $bContents = 'Modified b contents';
            file_put_contents($b, $bContents);

            $uniqueHash = md5("|$a?" . filemtime($a) . "|$b?" . filemtime($b));
            $prevCacheFile = $cacheFile;
            $cacheFile = $this->getCacheDir() . "combine/$deleteHash-$uniqueHash.js";

            $this->assertEquals(
                array(
                    $cacheFile => array($a, $b)
                ),
                $strat->minify(array($a, $b))
            );

            $this->assertEquals(
                <<<END
$aContents

$bContents
END
                ,
                file_get_contents($cacheFile)
            );

            $this->assertFalse(is_file($prevCacheFile), "old cache file for the content has been deleted ({$cacheDir}{$prevCacheFile})");

            // get files in a different order
            $deleteHash = md5("|$b|$a");
            $uniqueHash = md5("|$b?" . filemtime($b) . "|$a?" . filemtime($a));
            $cacheFile = $this->getCacheDir() . "combine/$deleteHash-$uniqueHash.js";

            $this->assertEquals(
                array(
                       $cacheFile => array($b, $a),
                ),
                $strat->minify(array($b, $a))
            );

            $this->assertEquals(
                <<<END
$bContents

$aContents
END
                ,
                file_get_contents($cacheFile)
            );


            unlink($a);
            unlink($b);
        }


    }

    /**
     * @group slow
     */
    function testSrcPlusCombine() {

        $app = $this->startApp();

        $siteDir = $this->getSiteDir();
        mkdir($siteDir . 'script/');
        mkdir($siteDir . 'cache/');

        $srcFoo = $siteDir . 'script/foo_src.js';
        $minFoo = $siteDir . 'script/foo.js';

        $srcBar = $siteDir . 'script/bar_src.js';
        $minBar = $siteDir . 'script/bar.js';

        file_put_contents($minFoo, "MINIFIED-FOO");
        file_put_contents($srcFoo, "ORIGINAL-FOO");
        file_put_contents($minBar, "MINIFIED-BAR");
        file_put_contents($srcBar, "ORIGINAL-BAR");

        $page = new Octopus_Html_Page();
        $page->addJavascriptMinifier('src');
        $page->addJavascriptMinifier('combine');
        $page->addJavascript($minFoo);
        $page->addJavascript($minBar);

        $toTouch = array(
            array(
                array($minFoo, $minBar),
                array($minFoo, $minBar),
            ),

            array(
                array($srcBar),
                array($minFoo, $srcBar),
            ),

            array(
                array($srcFoo),
                array($srcFoo, $srcBar),
            ),

            array(
                array($minBar),
                array($srcFoo, $minBar)
            ),
        );

        foreach($toTouch as $args) {

            sleep(1);

            list($touch, $expected) = $args;
            foreach($touch as $file) {
                touch($file);
            }

            $js = $page->getJavascriptFiles();
            $this->assertEquals(1, count($js), "1 js file");
            $js = array_shift($js);

            $this->assertEquals(
                $expected,
                $js['unminified_files']
            );
        }

    }
}
