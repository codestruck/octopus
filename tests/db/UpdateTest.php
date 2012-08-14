<?php

/**
 * @group DB
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_DB_Update_Test extends PHPUnit_Framework_TestCase
{

    public function testIntoSetWhere() {

        $s = new Octopus_DB_Update();
        $s->table('foo');
        $s->set('foo', 'bar');
        $s->where('id = ?', 9);

        $this->assertEquals($s->getSql(), "UPDATE foo SET `foo` = ? WHERE id = ?");
    }

    public function testCompatFrom() {

        $s = new Octopus_DB_Update();
        $s->table('foo');
        $s->set('foo', 'bar');
        $s->where('id = ?', 9);

        $this->assertEquals($s->getSql(), "UPDATE foo SET `foo` = ? WHERE id = ?");
    }

    public function testMissingWhere() {

        $s = new Octopus_DB_Update();
        $s->table('foo');
        $s->set('foo', 'bar');

        $this->assertEquals($s->getSql(), '');
    }

    public function testSetQuotes() {

        $s = new Octopus_DB_Update();
        $s->magic_quotes = false;
        $s->table('foo');
        $s->set('foo', "bar's");
        $s->where('id = ?', 9);

        $expected = "UPDATE foo SET `foo` = ? WHERE id = ?";
        $this->assertEquals($expected, $s->getSql());
    }

    public function testSetQuestion() {

        $s = new Octopus_DB_Update();
        $s->magic_quotes = false;
        $s->table('foo');
        $s->set('foo', "bar's?");
        $s->where('id = ?', 9);

        $expected = "UPDATE foo SET `foo` = ? WHERE id = ?";
        $this->assertEquals($expected, $s->getSql());
    }

    public function testIntoSetWhereBackwards() {

        $s = new Octopus_DB_Update();
        $s->table('foo');
        $s->where('id = ?', 9);
        $s->set('foo', 'bar');

        $this->assertEquals($s->getSql(), "UPDATE foo SET `foo` = ? WHERE id = ?");
    }

}

?>
