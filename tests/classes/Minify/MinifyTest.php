<?php

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
                    $urlDir . 'file_src.js?' . filemtime($src) => array('/file.js')
                ),
                $strat->getMinifiedUrls(array('/file.js')),
                "dir: $dirName"
            );

            sleep(1);
            touch($file);

            $this->assertEquals(
                array(
                    $urlDir . 'file.js?' . filemtime($file) => array('/file.js')
                ),
                $strat->getMinifiedUrls(array('/file.js'))
            );

            unlink($file);

            // if only src exists, link to src
            $this->assertEquals(
                array(
                    $urlDir . 'file_src.js?' . filemtime($src) => array('/file.js')
                ),
                $strat->getMinifiedUrls(array('/file.js'))
            );

            unlink($src);

            $this->assertEquals(array(), $strat->getMinifiedUrls(array('/file.js')));
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
            $cacheFile = "combine/$deleteHash-$uniqueHash.js";

            $this->assertEquals(
                array(
                    '/cache/' . $cacheFile => array('/a.js', '/b.js'),
                ),
                $strat->getMinifiedUrls(array('/a.js', '/b.js'))
            );

            $this->assertEquals(
                <<<END
$aContents

$bContents
END
                ,
                file_get_contents($cacheDir . $cacheFile)
            );

            sleep(1);

            // Modify a file
            $bContents = 'Modified b contents';
            file_put_contents($b, $bContents);

            $deleteHash = md5("|$a|$b");
            $uniqueHash = md5("|$a?" . filemtime($a) . "|$b?" . filemtime($b));
            $prevCacheFile = $cacheFile;
            $cacheFile = "combine/$deleteHash-$uniqueHash.js";

            $this->assertEquals(
                array(
                    '/cache/' . $cacheFile => array('/a.js', '/b.js'),
                ),
                $strat->getMinifiedUrls(array('/a.js', '/b.js'))
            );

            $this->assertEquals(
                <<<END
$aContents

$bContents
END
                ,
                file_get_contents($cacheDir . $cacheFile)
            );

            $this->assertFalse(is_file($cacheDir . $prevCacheFile), "old cache file for the content has been deleted ({$cacheDir}{$prevCacheFile})");

            // get files in a different order
            $deleteHash = md5("|$b|$a");
            $uniqueHash = md5("|$b?" . filemtime($b) . "|$a?" . filemtime($a));
            $cacheFile = "combine/$deleteHash-$uniqueHash.js";

            $this->assertEquals(
                array(
                    '/cache/' . $cacheFile => array('/b.js', '/a.js'),
                ),
                $strat->getMinifiedUrls(array('/b.js', '/a.js'))
            );

            $this->assertEquals(
                <<<END
$bContents

$aContents
END
                ,
                file_get_contents($cacheDir . $cacheFile)
            );


            unlink($a);
            unlink($b);
        }


    }

}

?>