<?php

Octopus::loadExternal('smarty');

/**
 * @group smarty
 * @group smarty_url
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
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

    function testUseRequestURIAsDefaultPath() {
        $_SERVER['REQUEST_URI'] = '/foo';
        $test = '{url}';
        $expected = '/foo';
        $this->assertSmartyEquals($expected, $test, '', false, false);
    }

    function testReplaceArgsInRequestURI() {
        $_SERVER['REQUEST_URI'] = '/foo?something=bar&something_else=3';
        $test = '{url something=foo}';
        $expected = '/foo?something=foo&amp;something_else=3';
        $this->assertSmartyEquals($expected, $test, '', false, false);
    }

}
