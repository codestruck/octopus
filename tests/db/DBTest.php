<?php

/**
 * PHPUnit test case
 */
require_once 'PHPUnit/Framework/TestCase.php';

/**
 * @group DB
 */
class Octopus_DB_Test extends PHPUnit_Framework_TestCase
{
    function testLoad()
    {
        $this->assertTrue(class_exists('Octopus_DB'));
    }

    function testSingleton()
    {

        $db = Octopus_DB::singleton();
        $db->handle = null;
        $db = Octopus_DB::singleton();

        $this->assertTrue($db->driver->handle !== null);
    }

    function testSingletonEquality()
    {

        $db = Octopus_DB::singleton();
        $db2 = Octopus_DB::singleton();

        $this->assertTrue($db === $db2);
    }

    function testInternalQueryCount() {

        $db = Octopus_DB::singleton();

        $current = $db->queryCount;
        $db->query('SELECT 1', true);
        $this->assertEquals($current + 1, $db->queryCount);

    }

    function testRollbackTransaction() {

    	$t = new Octopus_DB_Schema_Writer('_transaction_test');
    	$t->newKey('id', true);
    	$t->newPrimaryKey('id');
    	$t->newTextSmall('name');
    	$t->create();

    	$db = Octopus_DB::singleton();
    	$db->beginTransaction();
    	$this->assertTrue($db->inTransaction(), 'should be in transaction after calling beginTransaction');

    	$db->query("INSERT INTO _transaction_test (name) VALUES('foo')");
    	$db->query("INSERT INTO _transaction_test (name) VALUES('bar')");

    	$s = new Octopus_DB_Select();
    	$s->table('_transaction_test');
    	$this->assertEquals(2, $s->numRows());

    	$db->rollbackTransaction();

    	$this->assertEquals(0, $s->numRows());

    }

    function testCommitTransaction() {

    	$t = new Octopus_DB_Schema_Writer('_transaction_test');
    	$t->newKey('id', true);
    	$t->newPrimaryKey('id');
    	$t->newTextSmall('name');
    	$t->create();

    	$db = Octopus_DB::singleton();
    	$db->beginTransaction();
    	$this->assertTrue($db->inTransaction(), 'should be in transaction after calling beginTransaction');

    	$db->query("INSERT INTO _transaction_test (name) VALUES('foo')");
    	$db->query("INSERT INTO _transaction_test (name) VALUES('bar')");

    	$s = new Octopus_DB_Select();
    	$s->table('_transaction_test');
    	$this->assertEquals(2, $s->numRows());

    	$db->commitTransaction();

    	$this->assertEquals(2, $s->numRows());


    }

}
