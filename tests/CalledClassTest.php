<?php

require_once(FUNCTIONS_DIR . 'compat.php');

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

class Calling extends CallingBase {

}

class CalledClassTest extends PHPUnit_Framework_TestCase
{
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

}

?>
