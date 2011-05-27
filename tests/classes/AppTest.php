<?php

class AppTests extends Octopus_App_TestCase {

    function testGetTheme() {

        $app = $this->startApp();
        $settings = $app->getSettings();

        $settings->reset('site.theme');

        $this->assertEquals('default', $app->getTheme());

        $settings->set('site.theme', 'foo');
        $this->assertEquals('foo', $app->getTheme());

        $this->assertEquals('foo', $app->getTheme('/admin'));

        $settings->set('site.theme.admin', 'bar');
        $this->assertEquals('foo', $app->getTheme('/'));

        $this->assertEquals('bar', $app->getTheme('/admin'));
        $this->assertEquals('bar', $app->getTheme('/admin/'));
        $this->assertEquals('bar', $app->getTheme('/admin/whatever'));

    }

}

?>
