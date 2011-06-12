<?php

/**
 * @group core
 * @group security
 */
class SecurityTests extends PHPUnit_Framework_TestCase
{
    function testSalt() {
        $salt = get_salt();
        $this->assertTrue(strlen($salt) > 4, 'Salt is empty');
        $saltAgain = get_salt();
        $this->assertEquals($salt, $saltAgain, 'Repeat calls to get_salt() do not match');
    }

    function testRepeatToken() {
        $user_id = 5;
        $action = 'test_action';
        $token = get_security_token($user_id, $action);
        $this->assertTrue(strlen($token) > 4, 'Token is empty');
        $tokenAgain = get_security_token($user_id, $action);
        $this->assertEquals($token, $tokenAgain, 'Repeat calls to get_security_token() do not match');
    }

    function testRoundtrip() {
        $user_id = 5;
        $action = 'test_action';
        $token = get_security_token($user_id, $action);
        $this->assertEquals(1, verify_security_token($token, $user_id, $action));
    }

    function testRoundtripModified() {
        $user_id = 5;
        $action = 'test_action';
        $token = get_security_token($user_id, $action);
        $this->assertFalse(verify_security_token($token . 'ALTERED', $user_id, $action));
    }

    function testUrls() {
        $user_id = 5;
        $action = 'test_action';
        $token = get_security_token($user_id, $action);

        $url = '/foo/bar';
        $expect = '/foo/bar?__security_token=' . $token;
        $this->assertEquals($expect, get_security_url($url, $user_id, $action));

        $url = '/foo/bar?some_id=99';
        $expect = '/foo/bar?some_id=99&amp;__security_token=' . $token;
        $this->assertEquals($expect, get_security_url($url, $user_id, $action));

    }

}
