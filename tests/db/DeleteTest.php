<?php

/**
 * @group DB
 */
class Octopus_DB_Delete_Test extends PHPUnit_Framework_TestCase
{

    public function __construct()
    {
    }

    public function testIntoSetWhere() {

        $s = new Octopus_DB_Delete();
        $s->table('foo');
        $s->where('id = ?', 9);

        $this->assertEquals($s->getSql(), "DELETE FROM foo WHERE id = ?");
    }

    public function testIntoSetWhereQuote() {

        $s = new Octopus_DB_Delete();
        $s->magic_quotes = false;
        $s->table('foo');
        $s->where('id = ?', "Billy's");

        $this->assertEquals($s->getSql(), "DELETE FROM foo WHERE id = ?");
    }

    public function testIntoSetWhereQuestion() {

        $s = new Octopus_DB_Delete();
        $s->table('foo');
        $s->where('id = ?', 'what?');
        $s->where('b = ?', 'abc');

        $this->assertEquals($s->getSql(), "DELETE FROM foo WHERE id = ? AND b = ?");
    }

    public function testMissingWhere() {

        $s = new Octopus_DB_Delete();
        $s->table('foo');

        $this->assertEquals($s->getSql(), '');
    }

}

?>
