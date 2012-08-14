<?php

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class CalledClassTest extends PHPUnit_Framework_TestCase {

    function testMethodCalls()
    {
        $c = new Calling();
        $this->assertEquals('Calling', $c->d());
        $this->assertEquals('Calling', $c->e());
        $this->assertEquals('Calling', $c->f());
    }

    function testStaticCalls()
    {
        $this->assertEquals('Calling', Calling::a());
        $this->assertEquals('Calling', Calling::b());
        $this->assertEquals('Calling', Calling::c());
    }

    function testMultilineStaticCalls()
    {
        $this->assertEquals('Calling', Calling::a(array(
            'foo' => 'bar',
        )));
    }

}

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class CallingBase {

    static function a() {
        return get_called_class();
    }

    static function b() {
        return self::a();
    }

    static function c() {
        return self::b();
    }

    function d() {
        return self::a();
    }

    function e() {
        return self::c();
    }

    function f() {
        return $this->d();
    }

}

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Calling extends CallingBase {

}

