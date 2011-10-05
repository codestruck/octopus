<?php

/**
 * @group live_insert
 * @group DB
 */
class Octopus_DB_LiveInsert_Test extends PHPUnit_Framework_TestCase
{

    function setUp()
    {

        $this->db =& Octopus_DB::singleton();

        $sql = "DROP TABLE IF EXISTS test";
        $this->db->query($sql);

        $sql = "CREATE TABLE test (
`test_id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
`test_name` VARCHAR( 128 ) NOT NULL,
`test_date` DATETIME
)
";

        $this->db->query($sql);

    }

    function testInsertBasic()
    {
        $i = new Octopus_DB_Insert();
        $i->table('test');
        $i->set('test_name', 'asdf');
        $i->execute();

        $test_id = $i->getId();

        $this->assertEquals($test_id, 1);

        $s = new Octopus_DB_Select();
        $s->table('test');
        $s->where('test_id = ?', $test_id);
        $result = $s->fetchRow();

        $this->assertEquals('asdf', $result['test_name']);
        $this->assertEquals(1, $result['test_id']);

    }

    /**
     * @group DB_Double
     */
    function testInsertDouble()
    {
        $i = new Octopus_DB_Insert();
        $i->table('test');
        $i->set('test_name', 'asdf');
        $i->execute();
        $i->execute();

        $s = new Octopus_DB_Select();
        $s->table('test');
        $query = $s->query();

        $this->assertEquals(2, $query->numRows());

    }

    /**
     * @expectedException Octopus_Exception
     */
    function testInsertNoTable()
    {
        $old = db_error_reporting(DB_NONE);

        $i = new Octopus_DB_Insert();
        $i->table('test_not_found');
        $i->set('test_name', 'asdf');
        $i->execute();

        $test_id = $i->getId();

        db_error_reporting($old);

        $this->assertEquals($test_id, null);

    }

    function testInsertQuestion()
    {
        $i = new Octopus_DB_Insert();
        $i->table('test');
        $i->set('test_name', 'asdf?');
        $i->execute();

        $test_id = $i->getId();

        $this->assertEquals($test_id, 1);

        $s = new Octopus_DB_Select();
        $s->table('test');
        $s->where('test_id = ?', $test_id);
        $result = $s->fetchRow();

        $this->assertEquals('asdf?', $result['test_name']);
        $this->assertEquals(1, $result['test_id']);

    }

    function testInsertNow()
    {
        $i = new Octopus_DB_Insert();
        $i->table('test');
        $i->setNow('test_date');
        $i->execute();

        $test_id = $i->getId();

        $this->assertEquals($test_id, 1);

        $s = new Octopus_DB_Select();
        $s->table('test');
        $s->where('test_id = ?', $test_id);
        $result = $s->fetchRow();

        $time = strtotime($result['test_date']);

        $this->assertLessThanOrEqual(time(), $time);
        $this->assertGreaterThanOrEqual(time() - 10, $time);

        $this->assertEquals(1, $result['test_id']);

    }

}

?>
