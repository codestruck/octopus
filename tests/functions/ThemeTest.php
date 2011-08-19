<?php

class ThemeTest extends Octopus_App_TestCase {

    function initSiteDir() {

        parent::initSiteDir();

        @mkdir($this->getSiteDir() . 'themes/test', 0777, true);
    }


    function testGetThemeFile() {

        touch($this->getSiteDir() . 'themes/test/foo.bar');

        $file = get_theme_file('foo.bar', array('SITE_DIR' => $this->getSiteDir(), 'theme' => 'test'));

        $this->assertEquals($this->getSiteDir() . 'themes/test/foo.bar', $file);
    }

    function testGetThemeFileWithSrc() {

        $og = $this->getSiteDir() . 'themes/test/styles.css';
        $src = $this->getSiteDir() . 'themes/test/styles_src.css';
        $options = array('SITE_DIR' => $this->getSiteDir(), 'theme' => 'test', 'use_src' => true);

        touch($og); sleep(1); touch($src);

        $file = get_theme_file('styles.css', $options);
        $this->assertEquals($src, $file, 'failed with newer src');

        sleep(1); touch($og);
        $file = get_theme_file('styles.css', $options);
        $this->assertEquals($og, $file, 'failed with newer og');

        @mkdir($this->getSiteDir() . 'themes/test/subdir');

        $og = $this->getSiteDir() . 'themes/test/subdir/styles.css';
        $src = $this->getSiteDir() . 'themes/test/subdir/styles_src.css';

        touch($og); sleep(1); touch($src);

        $file = get_theme_file('subdir/styles.css', $options);
        $this->assertEquals($src, $file, 'failed with newer src in subdir');

        sleep(1); touch($og);
        $file = get_theme_file('subdir/styles.css', $options);
        $this->assertEquals($og, $file, 'failed with newer og in subdir');
    }

    function testGetThemeFileUrl() {

        touch($this->getSiteDir() . 'themes/test/url_test.css');

        $this->assertEquals(
            '/url-base/site/themes/test/url_test.css',
            get_theme_file_url(
                'url_test.css',
                array(
                    'SITE_DIR' => $this->getSiteDir(),
                    'URL_BASE' => '/url-base/',
                    'theme' => 'test'
                )
            )
        );


        $this->assertEquals(
            '/site/themes/test/url_test.css',
            get_theme_file_url(
                'url_test.css',
                array(
                    'SITE_DIR' => $this->getSiteDir(),
                    'URL_BASE' => '/',
                    'theme' => 'test'
                )
            )
        );
    }

}

?>
