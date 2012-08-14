<?php

/**
 * @group DB
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_DB_LiveSelectJoin_Test extends PHPUnit_Framework_TestCase
{

    public function __construct()
    {

        $this->db = Octopus_DB::singleton();

        $sql = "DROP TABLE IF EXISTS apples";
        $this->db->query($sql);
        $sql = "DROP TABLE IF EXISTS bananas";
        $this->db->query($sql);
        $sql = "DROP TABLE IF EXISTS carrots";
        $this->db->query($sql);

        $sql = "CREATE TABLE apples (
`apple_id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
`apple_name` VARCHAR( 128 ) NOT NULL,
`apple_foo` VARCHAR( 128 ) NOT NULL
)
";
        $this->db->query($sql);

        $sql = "CREATE TABLE bananas (
`banana_id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
`apple_id` INT( 10 ) NOT NULL,
`banana_name` VARCHAR( 128 ) NOT NULL,
`banana_foo` VARCHAR( 128 ) NOT NULL
)
";
        $this->db->query($sql);

        $sql = "CREATE TABLE carrots (
`carrot_id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
`apple_id` INT( 10 ) NOT NULL,
`carrot_name` VARCHAR( 128 ) NOT NULL
)
";
        $this->db->query($sql);


    }

    function setUp()
    {

        $i = new Octopus_DB_Insert();
        $i->table('apples');
        $i->set('apple_name', 'aoeu');
        $i->execute();
        $apple_id = $i->getId();

        $i = new Octopus_DB_Insert();
        $i->table('bananas');
        $i->set('banana_name', 'ba');
        $i->set('apple_id', $apple_id);
        $i->execute();

        $i = new Octopus_DB_Insert();
        $i->table('bananas');
        $i->set('banana_name', 'no apple');
        $i->execute();

    }

    function tearDown()
    {
        $sql = "TRUNCATE TABLE apples";
        $this->db->query($sql);
        $sql = "TRUNCATE TABLE bananas";
        $this->db->query($sql);
        $sql = "TRUNCATE TABLE carrots";
        $this->db->query($sql);
    }

    function testJoin()
    {
        $s = new Octopus_DB_Select();
        $s->table(array('bananas', 'b'));
        $s->leftJoin(array('apples', 'a'), 'apple_id');
        $query = $s->query();
        $this->assertEquals(2, $query->numRows());

        $s = new Octopus_DB_Select();
        $s->table(array('bananas', 'b'));
        $s->innerJoin(array('apples', 'a'), 'apple_id');
        $query = $s->query();
        $this->assertEquals(1, $query->numRows());

    }

    function testJoinFields()
    {
        $s = new Octopus_DB_Select();
        $s->table(array('bananas', 'b'));
        $s->leftJoin(array('apples', 'a'), 'apple_id', array('apple_name'));
        $query = $s->query();
        $this->assertEquals(2, $query->numRows());
        $all = $s->fetchAll();
        $row = $all[0];
        $this->assertEquals(5, count($row));
        $this->assertArrayHasKey('banana_id', $row);
        $this->assertArrayHasKey('banana_name', $row);
        $this->assertArrayHasKey('banana_foo', $row);
        $this->assertArrayHasKey('apple_id', $row);
        $this->assertArrayHasKey('apple_name', $row);

        $s = new Octopus_DB_Select();
        $s->table(array('bananas', 'b'), array('banana_name'));
        $s->innerJoin(array('apples', 'a'), array('apple_id'), array('apple_name'));
        $query = $s->query();
        $this->assertEquals(1, $query->numRows());
        $row = $s->fetchRow();
        $this->assertEquals(2, count($row));
        $this->assertArrayHasKey('banana_name', $row);
        $this->assertArrayHasKey('apple_name', $row);

        $s = new Octopus_DB_Select();
        $s->table(array('bananas', 'b'), array('banana_name'));
        $s->innerJoin(array('apples', 'a'), 'apple_id');
        $query = $s->query();
        $this->assertEquals(1, $query->numRows());
        $row = $s->fetchRow();
        $this->assertEquals(4, count($row));
        $this->assertArrayHasKey('banana_name', $row);
        $this->assertArrayHasKey('apple_id', $row);
        $this->assertArrayHasKey('apple_name', $row);
        $this->assertArrayHasKey('apple_foo', $row);

    }

    function testJoinRunFunction()
    {

        $s = new Octopus_DB_Select();
        $s->table('a', array('cat_id', 'name'));
        $s->leftJoin('b', 'cat_id', array('article_id'));
        $s->runFunction('count', 'b', array('article_id', 'count'));

        $sql = 'SELECT a.cat_id, a.name, COUNT(b.article_id) AS count FROM a LEFT JOIN b USING (cat_id)';

        $this->assertEquals($sql, $s->getSql());

    }

}

?>
