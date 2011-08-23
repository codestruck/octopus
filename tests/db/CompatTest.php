<?php

Octopus::loadClass('Octopus_DB_Delete');
Octopus::loadClass('Octopus_DB_Insert');
Octopus::loadClass('Octopus_DB_Select');
Octopus::loadClass('Octopus_DB_Update');

/**
 * @group DB
 */
class Octopus_DB_Compat_Test extends PHPUnit_Framework_TestCase
{

    function testDelete() {

        $i = new Octopus_DB_Delete();
        $this->assertFalse(method_exists($i, 'from'));
        $this->assertFalse(method_exists($i, 'into'));
        $this->assertTrue(method_exists($i, 'table'));

        $this->assertFalse(method_exists($i, 'perform'));
        $this->assertTrue(method_exists($i, 'execute'));
        $this->assertTrue(method_exists($i, 'query'));

    }

    function testInsert() {

        $i = new Octopus_DB_Insert();
        $this->assertFalse(method_exists($i, 'from'));
        $this->assertFalse(method_exists($i, 'into'));
        $this->assertTrue(method_exists($i, 'table'));

        $this->assertFalse(method_exists($i, 'perform'));
        $this->assertTrue(method_exists($i, 'execute'));
        $this->assertTrue(method_exists($i, 'query'));

    }

    function testSelect() {

        $i = new Octopus_DB_Select();
        $this->assertFalse(method_exists($i, 'from'));
        $this->assertFalse(method_exists($i, 'into'));
        $this->assertTrue(method_exists($i, 'table'));

        $this->assertFalse(method_exists($i, 'perform'));
        $this->assertTrue(method_exists($i, 'execute'));
        $this->assertTrue(method_exists($i, 'query'));

    }

    function testUpdate() {

        $i = new Octopus_DB_Update();
        $this->assertFalse(method_exists($i, 'from'));
        $this->assertFalse(method_exists($i, 'into'));
        $this->assertTrue(method_exists($i, 'table'));

        $this->assertFalse(method_exists($i, 'perform'));
        $this->assertTrue(method_exists($i, 'execute'));
        $this->assertTrue(method_exists($i, 'query'));

    }

}

?>
