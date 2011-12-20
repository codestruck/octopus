<?php

/**
 * @group DB
 */
class Octopus_DB_LiveSelect_Test extends PHPUnit_Framework_TestCase
{

    function setUp()
    {

        $this->db = Octopus_DB::singleton();

        $sql = "DROP TABLE IF EXISTS test";
        $this->db->query($sql);

        $sql = "CREATE TABLE test (
`test_id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
`test_name` VARCHAR( 128 ) NOT NULL,
`test_alt` VARCHAR( 128 ) NOT NULL,
`test_num` INT NOT NULL
)
";

        $this->db->query($sql);

    }

    function tearDown()
    {
//        $sql = "TRUNCATE TABLE test";
//        $this->db->query($sql);
    }

    function testSelectBasic()
    {
        $sql = "INSERT INTO test SET test_name = 'testSelect'";
        $this->db->query($sql);

        $select = new Octopus_DB_Select();
        $select->table('test');
        $query = $select->query();
        $result = $query->fetchRow();

        $this->assertEquals('testSelect', $result['test_name']);
        $this->assertEquals(1, $result['test_id']);

    }

    function testSelectBasicConstructor()
    {
        $sql = "INSERT INTO test SET test_name = 'testSelect'";
        $this->db->query($sql);

        $sql = 'SELECT * FROM test WHERE test_id = ?';
        $args = array(1);

        $select = new Octopus_DB_Select($sql, $args);
        $query = $select->query();
        $result = $query->fetchRow();

        $this->assertEquals('testSelect', $result['test_name']);
        $this->assertEquals(1, $result['test_id']);

    }

    /**
     * @group DB_Double
     */
    function testSelectDouble()
    {
        $sql = "INSERT INTO test SET test_name = 'testSelect'";
        $this->db->query($sql);

        $select = new Octopus_DB_Select();
        $select->table('test');
        $select->where('test_id = ?', 1);
        $query = $select->query();

        if ($query->numRows() > 0) {
            $data = $select->fetchRow();
        }

        $this->assertEquals('testSelect', $data['test_name']);
        $this->assertEquals(1, $data['test_id']);

    }

    function testSelectBasicCompatExecute()
    {
        $sql = "INSERT INTO test SET test_name = 'testSelect'";
        $this->db->query($sql);

        $select = new Octopus_DB_Select();
        $select->table('test');
        $query = $select->execute();
        $result = $query->fetchRow();

        $this->assertEquals('testSelect', $result['test_name']);
        $this->assertEquals(1, $result['test_id']);

    }

    function testSelectBasicCompatPerform()
    {
        $sql = "INSERT INTO test SET test_name = 'testSelect'";
        $this->db->query($sql);

        $select = new Octopus_DB_Select();
        $select->table('test');
        $query = $select->execute();
        $result = $query->fetchRow();

        $this->assertEquals('testSelect', $result['test_name']);
        $this->assertEquals(1, $result['test_id']);

    }

    function testGetOne()
    {
        $sql = "INSERT INTO test SET test_name = 'testSelect'";
        $this->db->query($sql);

        $select = new Octopus_DB_Select();
        $select->table('test', array('test_name'));
        $test_name = $select->getOne();

        $this->assertEquals('testSelect', $test_name);

    }

    function testFetchAll()
    {
        $sql = "INSERT INTO test SET test_name = 'testSelect'";
        $this->db->query($sql);
        $sql = "INSERT INTO test SET test_name = 'testSelect'";
        $this->db->query($sql);

        $select = new Octopus_DB_Select();
        $select->table('test');
        $data = $select->fetchAll();

        $this->assertEquals(2, count($data));

    }

    function testFetchAll2()
    {
        $sql = "TRUNCATE TABLE test";
        $this->db->query($sql);

        $s = new Octopus_DB_Select();
        $s->table('test');
        $all = $s->fetchAll();
        $this->assertEquals(0, count($all));

        $sql = "INSERT INTO test SET test_name = 'what'";
        $this->db->query($sql);

        $s = new Octopus_DB_Select();
        $s->table('test');
        $all = $s->fetchAll();
        $this->assertEquals(1, count($all));
        $item = array_shift($all);
        $this->assertEquals('what', $item['test_name']);

    }

    function testWhere()
    {
        $sql = "INSERT INTO test SET test_name = 'testWhere'";
        $this->db->query($sql);
        $sql = "INSERT INTO test SET test_name = 'testWhere2'";
        $this->db->query($sql);

        $select = new Octopus_DB_Select();
        $select->table('test', array('test_id'));
        $select->where("test_name = 'testWhere'");
        $test_id = $select->getOne();

        $this->assertEquals(1, $test_id);

        $select = new Octopus_DB_Select();
        $select->table('test', array('test_id'));
        $select->where("test_name = 'testWhere2'");
        $test_id = $select->getOne();

        $this->assertEquals(2, $test_id);

    }

    function testWhereTwoFields()
    {

        $sql = "INSERT INTO test SET test_name = 'testWhere'";
        $this->db->query($sql);
        $sql = "INSERT INTO test SET test_name = 'testWhere2'";
        $this->db->query($sql);

        // start actual test

        $select = new Octopus_DB_Select();
        $select->table('test', array('test_id', 'test_name'));

        $query = $select->query();

        $this->assertEquals(2, $query->numRows());

        // end actual test

    }

    function testWhereTwoTables()
    {
        $sql = "DROP TABLE IF EXISTS test2";
        $this->db->query($sql);

        $sql = "CREATE TABLE test2 (
`test_id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
`test_name` VARCHAR( 128 ) NOT NULL
)
";

        $this->db->query($sql);


        $sql = "INSERT INTO test SET test_name = 'testWhere'";
        $this->db->query($sql);
        $sql = "INSERT INTO test SET test_name = 'testWhere2'";
        $this->db->query($sql);

        $sql = "INSERT INTO test2 SET test_name = 'testWhere'";
        $this->db->query($sql);
        $sql = "INSERT INTO test2 SET test_name = 'testWhere2'";
        $this->db->query($sql);

        // start actual test

        $select = new Octopus_DB_Select();
        $select->table('test');
        $select->table('test2');
        $query = $select->query();

        $this->assertEquals(4, $query->numRows());

        // end actual test

        $sql = "DROP TABLE test2";
        $this->db->query($sql);
    }

    function testWhereTwoTablesTwoFields()
    {
        $sql = "DROP TABLE IF EXISTS test2";
        $this->db->query($sql);

        $sql = "CREATE TABLE test2 (
`test2_id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
`test2_name` VARCHAR( 128 ) NOT NULL
)
";

        $this->db->query($sql);


        $sql = "INSERT INTO test SET test_name = 'testWhere'";
        $this->db->query($sql);
        $sql = "INSERT INTO test SET test_name = 'testWhere2'";
        $this->db->query($sql);

        $sql = "INSERT INTO test2 SET test2_name = 'testWhere'";
        $this->db->query($sql);
        $sql = "INSERT INTO test2 SET test2_name = 'testWhere2'";
        $this->db->query($sql);

        // start actual test

        $select = new Octopus_DB_Select();
        $select->table('test', array('test_id', 'test_name'));
        $select->table('test2', array('test2_id', 'test2_name'));
        $query = $select->query();

        $this->assertEquals(4, $query->numRows());

        // end actual test

        $sql = "DROP TABLE test2";
        $this->db->query($sql);
    }

    function testWhereTwoParams()
    {

        $sql = "INSERT INTO test SET test_name = 'testWhere'";
        $this->db->query($sql);
        $sql = "INSERT INTO test SET test_name = 'testWhere2'";
        $this->db->query($sql);

        // test multiple args

        $s = new Octopus_DB_Select();
        $s->table('test');
        $s->where('test_name = ? OR test_name = ?', 'testWhere', 'testWhere2');

        $sql = $s->getSql();
        $expect = "SELECT * FROM test WHERE test_name = ? OR test_name = ?";
        $this->assertEquals($expect, $sql);

        $query = $s->query();

        $this->assertEquals(2, $query->numRows());

        // test array
        $s = new Octopus_DB_Select();
        $s->table('test');
        $s->where('test_name = ? OR test_name = ?', array('testWhere', 'testWhere2'));

        $sql = $s->getSql();
        $expect = "SELECT * FROM test WHERE test_name = ? OR test_name = ?";
        $this->assertEquals($expect, $sql);

        $query = $s->query();

        $this->assertEquals(2, $query->numRows());


        // end actual test

    }

    function testGetOneArray()
    {

        $sql = "INSERT INTO test SET test_name = 'testWhere'";
        $this->db->query($sql);
        $sql = "INSERT INTO test SET test_name = 'testWhere2'";
        $this->db->query($sql);

        // test get one array of values
        $select = new Octopus_DB_Select();
        $select->table('test', array('test_name'));

        $data = $select->getOneArray();

        $this->assertEquals(2, count($data));
        $this->assertEquals('testWhere', $data[0]);
        $this->assertEquals('testWhere2', $data[1]);

        // test get one array of ids with multi-column select
        $select = new Octopus_DB_Select();
        $select->table('test', array('test_id', 'test_name'));

        $data = $select->getOneArray();

        $this->assertEquals(2, count($data));
        $this->assertEquals('1', $data[0]);
        $this->assertEquals('2', $data[1]);

        // test get one array of ids with columns not specified
        $select = new Octopus_DB_Select();
        $select->table('test');

        $data = $select->getOneArray();

        $this->assertEquals(2, count($data));
        $this->assertEquals('1', $data[0]);
        $this->assertEquals('2', $data[1]);


    }

    function testGetMap()
    {

        $sql = "INSERT INTO test SET test_name = 'testWhere', test_alt = 'foo'";
        $this->db->query($sql);
        $sql = "INSERT INTO test SET test_name = 'testWhere2', test_alt = 'bar'";
        $this->db->query($sql);

        // test get one array of ids with multi-column select
        $select = new Octopus_DB_Select();
        $select->table('test', array('test_alt', 'test_name'));

        $data = $select->getMap();

        $this->assertEquals(2, count($data));
        $this->assertArrayHasKey('foo', $data);
        $this->assertArrayHasKey('bar', $data);
        $this->assertEquals('testWhere', $data['foo']);
        $this->assertEquals('testWhere2', $data['bar']);

    }

    function testFetchObject()
    {

        $sql = "INSERT INTO test SET test_name = 'testFetchObject', test_alt = 'foo'";
        $this->db->query($sql);

        // test get one array of ids with multi-column select
        $select = new Octopus_DB_Select();
        $select->table('test');

        $obj = $select->fetchObject();

        $this->assertTrue(is_object($obj));
        $this->assertFalse(is_array($obj));
        $this->assertTrue(property_exists($obj, 'test_id'));
        $this->assertEquals(1, $obj->test_id);
        $this->assertTrue(property_exists($obj, 'test_name'));
        $this->assertEquals('testFetchObject', $obj->test_name);
        $this->assertTrue(property_exists($obj, 'test_alt'));
        $this->assertEquals('foo', $obj->test_alt);

    }

    function testFetchObjectNoData()
    {

        $sql = "INSERT INTO test SET test_name = 'testFetchObject', test_alt = 'foo'";
        $this->db->query($sql);

        // test get one array of ids with multi-column select
        $select = new Octopus_DB_Select();
        $select->table('test');
        $select->where('test_id = ?', 9999999);

        $obj = $select->fetchObject();

        $this->assertTrue($obj === false);
        $this->assertFalse(is_object($obj));
        $this->assertFalse(is_array($obj));

    }

    function testHaving()
    {
        $sql = "INSERT INTO test SET test_name = 'testFetchObject', test_alt = 'foo', test_num = 1";
        $this->db->query($sql);

        $sql = "INSERT INTO test SET test_name = 'testFetchObject', test_alt = 'foo', test_num = 2";
        $this->db->query($sql);

        $sql = "INSERT INTO test SET test_name = 'testFetchObject', test_alt = 'foo', test_num = 2";
        $this->db->query($sql);

        $s = new Octopus_DB_Select();
        $s->table('test');
        $s->groupBy('test_num');
        $s->having('test_num = ?', 2);

        $query = $s->query();
        $this->assertEquals(1, $query->numRows());
    }

}

?>
