<?php

/**
 * @group DB
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_DB_Insert_Test extends PHPUnit_Framework_TestCase
{

    function testBasic() {

        $i = new Octopus_DB_Insert();
        $i->table('foo');
        $i->set('foo', 'bar');

        $this->assertEquals($i->getSql(), "INSERT INTO foo SET `foo` = ?");
    }

    function testNumeric() {

        $i = new Octopus_DB_Insert();
        $i->table('foo');
        $i->set('foo', 1);

        $this->assertEquals($i->getSql(), "INSERT INTO foo SET `foo` = ?");
    }

    function testRaw() {

        $i = new Octopus_DB_Insert();
        $i->table('foo');
        $i->setRaw('foo', 'foo_count + 1');

        $this->assertEquals($i->getSql(), "INSERT INTO foo SET `foo` = foo_count + 1");
    }

}

?>
