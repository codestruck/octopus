<?php

/**
 * @group core
 */
class FlashTests extends PHPUnit_Framework_TestCase
{

    function testDefaultType() {
        set_flash('message 1');
        $flash = get_flash();
        $this->assertEquals('message 1', $flash['content']);
        $this->assertEquals(0, count($flash['options']));
    }

    function testSuccessIsDefault() {
        set_flash('message 2', 'success');
        $flash = get_flash();
        $this->assertEquals('message 2', $flash['content']);
        $this->assertEquals(0, count($flash['options']));
    }

    function testGetFlashSkipsCustom() {
        set_flash('message 3', 'custom');
        $flash = get_flash();
        $this->assertEquals('', $flash);
    }

    function testCustomType() {
        set_flash('message 4', 'custom');
        $flash = get_flash('custom');
        $this->assertEquals('message 4', $flash['content']);
        $this->assertEquals(0, count($flash['options']));
    }

    function testRenderDefaultType() {

        $this->expectOutputString('        <div class="flash success">

            <div class="flashContent">message 5</div>
        </div>');

        set_flash('message 5');
        $flash = render_flash();
        $this->assertEquals('', $flash);
    }

    function testRenderSuccessType() {

        $this->expectOutputString('        <div class="flash success">

            <div class="flashContent">message 6</div>
        </div>');

        set_flash('message 6', 'success');
        $flash = render_flash();
        $this->assertEquals('', $flash);
    }

    function testRenderCustomType() {

        $this->expectOutputString('        <div class="flash custom">

            <div class="flashContent">message 7</div>
        </div>');

        set_flash('message 7', 'custom');
        $flash = render_flash();
        $this->assertEquals('', $flash);
    }

    function testRenderJustCustomType() {

        $this->expectOutputString('        <div class="flash custom">

            <div class="flashContent">message 7</div>
        </div>');

        set_flash('message 7', 'custom');
        $flash = render_flash('custom');
        $this->assertEquals('', $flash);
    }

    function testRenderSkipCustomType() {

        $this->expectOutputString('');

        set_flash('message 7', 'custom');
        $flash = render_flash('success');
        $this->assertEquals('', $flash);
    }


}
