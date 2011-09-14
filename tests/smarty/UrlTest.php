<?php

Octopus::loadExternal('smarty');

/**
 * @group smarty
 * @group smarty_url
 */
class UrlTest extends Octopus_App_TestCase {

    function testBasic() {

        $test = '{url path="/user/login"}';
        $expected = '/user/login';
        $this->assertSmartyEquals($expected, $test);
    }

    function testDefault() {

        $assign = array();
        $assign['defaults'] = array('user' => 'mike', 'color' => 'green');
        $test = '{url path="/user/login" default=$defaults}';
        $expected = '/user/login?user=mike&amp;color=green';
        $this->assertSmartyEquals($expected, $test, '', false, false, $assign);
    }

    function testNewValue() {

        $assign = array();
        $assign['defaults'] = array('user' => 'mike', 'color' => 'green');
        $test = '{url path="/user/login" size=large default=$defaults}';
        $expected = '/user/login?user=mike&amp;color=green&amp;size=large';
        $this->assertSmartyEquals($expected, $test, '', false, false, $assign);
    }

    function testOverwriteValue() {

        $assign = array();
        $assign['defaults'] = array('user' => 'mike', 'color' => 'green');
        $test = '{url path="/user/login" color=blue default=$defaults}';
        $expected = '/user/login?user=mike&amp;color=blue';
        $this->assertSmartyEquals($expected, $test, '', false, false, $assign);
    }

    function testEnding() {

        $test = '{url path="/user/login" ending=true}';
        $expected = '/user/login?';
        $this->assertSmartyEquals($expected, $test);

        $assign = array();
        $assign['defaults'] = array('user' => 'mike', 'color' => 'green');
        $test = '{url path="/user/login" default=$defaults ending=true}';
        $expected = '/user/login?user=mike&amp;color=green&amp;';
        $this->assertSmartyEquals($expected, $test, '', false, false, $assign);

    }

    function testAltSep() {

        $assign = array();
        $assign['defaults'] = array('user' => 'mike', 'color' => 'green');
        $test = '{url path="/user/login" default=$defaults sep="&"}';
        $expected = '/user/login?user=mike&color=green';
        $this->assertSmartyEquals($expected, $test, '', false, false, $assign);

    }

    function testRemoveArg() {

        $assign = array();
        $assign['defaults'] = array('user' => 'mike', 'color' => 'green');
        $test = '{url path="/user/login" default=$defaults remove_arg=color}';
        $expected = '/user/login?user=mike';
        $this->assertSmartyEquals($expected, $test, '', false, false, $assign);

    }

    function testEscape() {

        $assign = array();
        $assign['defaults'] = array('user' => 'with space');
        $test = '{url path="/user/login" default=$defaults new="also spaced"}';
        $expected = '/user/login?user=with%20space&amp;new=also%20spaced';
        $this->assertSmartyEquals($expected, $test, '', false, false, $assign);
    }

    // MOVE TO COMMON CLASS OR APP CLASS
    function assertSmartyEquals($expected, $value, $message = '', $replaceMD5 = false, $replaceMtime = false, $assign = array()) {

        $app = $this->getApp();

        $s = Octopus_Smarty::singleton();

        $smartyDir = $this->getSiteDir() . 'smarty/';
        @mkdir($smartyDir);

        $tplFile = $smartyDir . 'test.' . md5($expected) . '.tpl';
        @unlink($tplFile);

        file_put_contents($tplFile, $value);

        $s->smarty->template_dir = array($smartyDir);
        $tpl = $s->smarty->createTemplate($tplFile, $assign);

        $rendered = $tpl->fetch();

        if ($replaceMD5) {
            $rendered = preg_replace('/[a-f\d]{32}/i', '[MD5]', $rendered);
        }

        if ($replaceMtime) {
            $rendered = preg_replace('/\d{10,}/', '[MTIME]', $rendered);
        }

        $this->assertHtmlEquals($expected, $rendered, $message);
    }

}