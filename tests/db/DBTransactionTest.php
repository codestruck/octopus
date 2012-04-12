<?php

class TransactionRollbackTestException extends Exception { }

class DBTransactionTest extends PHPUnit_Framework_TestCase {

	function setUp() {

		parent::setUp();

		$db = Octopus_DB::singleton();
		$db->query('DROP TABLE IF EXISTS transaction_test_table;');
		$db->query('

			CREATE TABLE transaction_test_table (
				`id` int(11) NOT NULL PRIMARY KEY,
				`name` varchar(255)
			) ENGINE=InnoDB;

		');

		$tx = $db->getTransaction();
		if ($tx) $tx->rollback();

	}

	function testCommitTransaction() {

		$db = Octopus_DB::singleton();

		$tx = $db->beginTransaction();

		$i = new Octopus_DB_Insert();
		$i->table('transaction_test_table');
		$i->set('name', 'foo');
		$i->execute();

		$this->assertEquals('foo', $db->getOne('SELECT name FROM transaction_test_table'), 'record found');
		$this->assertEquals(1, $db->getOne('SELECT COUNT(*) FROM transaction_test_table'), 'count is correct');

		$this->assertFalse($tx->isCommitted(), 'not committed before commit() call');
		$tx->commit();
		$this->assertTrue($tx->isCommitted(), 'committed after commit() call');

		$this->assertEquals('foo', $db->getOne('SELECT name FROM transaction_test_table'), 'record found after commit');
		$this->assertEquals(1, $db->getOne('SELECT COUNT(*) FROM transaction_test_table'), 'count is correct after commit');

	}

	function testRollBackTransaction() {

		$db = Octopus_DB::singleton();

		$tx = $db->beginTransaction();

		$i = new Octopus_DB_Insert();
		$i->table('transaction_test_table');
		$i->set('name', 'foo');
		$i->execute();

		$this->assertEquals('foo', $db->getOne('SELECT name FROM transaction_test_table'), 'record found');
		$this->assertEquals(1, $db->getOne('SELECT COUNT(*) FROM transaction_test_table'), 'count is correct');

		$this->assertFalse($tx->isRolledBack(), 'tx not marked as rolled back before rollback() call');
		$tx->rollback();
		$this->assertTrue($tx->isRolledBack(), 'tx marked as rolled back after rollback() call');

		$this->assertEquals(null, $db->getOne('SELECT name FROM transaction_test_table'), 'record not found after rollback');
		$this->assertEquals(0, $db->getOne('SELECT COUNT(*) FROM transaction_test_table'), 'count is correct after rollback');

	}

	function testCallbackImplicitCommit() {

		$db = Octopus_DB::singleton();
		$this->assertTrue($db->runTransaction(array($this, 'callbackThatCommitsImplicitly')));

		$this->assertEquals('foo', $db->getOne('SELECT name FROM transaction_test_table'), 'record found after commit');
		$this->assertEquals(1, $db->getOne('SELECT COUNT(*) FROM transaction_test_table'), 'count is correct after commit');

		$db->beginTransaction(); // make sure this doesn't throw an exception

	}

	function callbackThatCommitsImplicitly(Octopus_DB_Transaction $tx, Octopus_DB $db) {

		$i = new Octopus_DB_Insert();
		$i->table('transaction_test_table');
		$i->set('name', 'foo');
		$i->execute();

		// transaction is committed implicitly because we did not cancel it
	}

	function testCallbackExplicitCommit() {

		$db = Octopus_DB::singleton();
		$this->assertTrue($db->runTransaction(array($this, 'callbackThatCommitsExplicitly')));

		$this->assertEquals('foo', $db->getOne('SELECT name FROM transaction_test_table'), 'record found after commit');
		$this->assertEquals(1, $db->getOne('SELECT COUNT(*) FROM transaction_test_table'), 'count is correct after commit');

		$db->beginTransaction(); // make sure this doesn't throw an exception

	}

	function callbackThatCommitsExplicitly(Octopus_DB_Transaction $tx, Octopus_DB $db) {

		$i = new Octopus_DB_Insert();
		$i->table('transaction_test_table');
		$i->set('name', 'foo');
		$i->execute();

		$tx->commit();

	}


	function testCallbackReturnValue() {

		$db = Octopus_DB::singleton();
		$this->assertTrue($db->runTransaction(array($this, 'callbackThatReturnsAValue'), $retval));
		$this->assertEquals('blarg', $retval);

	}

	function callbackThatReturnsAValue() {
		return "blarg";
	}

	function testCallbackRollback() {

		$db = Octopus_DB::singleton();
		$this->assertFalse($db->runTransaction(array($this, 'callbackThatRollsBackExplicitly')));

		$this->assertEquals(null, $db->getOne('SELECT name FROM transaction_test_table'), 'record not found after rollback');
		$this->assertEquals(0, $db->getOne('SELECT COUNT(*) FROM transaction_test_table'), 'count is correct after rollback');


	}

	function callbackThatRollsBackExplicitly(Octopus_DB_Transaction $tx, Octopus_DB $db) {

		$i = new Octopus_DB_Insert();
		$i->table('transaction_test_table');
		$i->set('name', 'foo');
		$i->execute();

		$this->assertEquals('foo', $db->getOne('SELECT name FROM transaction_test_table'), 'record found');
		$this->assertEquals(1, $db->getOne('SELECT COUNT(*) FROM transaction_test_table'), 'count is correct');

		$tx->rollback();

	}

	/**
	 * @expectedException Octopus_DB_Exception
	 */
	function testCommitAfterCommitThrowsException() {

		$db = Octopus_DB::singleton();

		$tx = $db->beginTransaction();

		$i = new Octopus_DB_Insert();
		$i->table('transaction_test_table');
		$i->set('name', 'foo');
		$i->execute();

		$tx->commit();

		$tx->commit(); // should throw exception

	}

	/**
	 * @expectedException Octopus_DB_Exception
	 */
	function testRollbackAfterCommitThrowsException() {

		$db = Octopus_DB::singleton();

		$tx = $db->beginTransaction();

		$i = new Octopus_DB_Insert();
		$i->table('transaction_test_table');
		$i->set('name', 'foo');
		$i->execute();

		$tx->commit();

		$tx->rollback(); // should throw exception

	}

	/**
	 * @expectedException Octopus_DB_Exception
	 */
	function testRollbackAfterRollbackThrowsException() {

		$db = Octopus_DB::singleton();

		$tx = $db->beginTransaction();

		$i = new Octopus_DB_Insert();
		$i->table('transaction_test_table');
		$i->set('name', 'foo');
		$i->execute();

		$tx->rollback();
		$tx->rollback(); // should throw exception

	}


	/**
	 * @expectedException Octopus_DB_Exception
	 */
	function testCommitAfterRollbackThrowsException() {

		$db = Octopus_DB::singleton();

		$tx = $db->beginTransaction();

		$i = new Octopus_DB_Insert();
		$i->table('transaction_test_table');
		$i->set('name', 'foo');
		$i->execute();

		$tx->rollback();
		$tx->commit(); // should throw exception

	}

	/**
	 * @expectedException Octopus_DB_Exception
	 */
	function testBeginTransactionWithUncommittedTxThrowsException() {

		$db = Octopus_DB::singleton();

		$tx = $db->beginTransaction();
		$tx = $db->beginTransaction();

	}

	/**
	 */
	function testBeginTransactionWithCommittedTxDoesNotThrowException() {

		$db = Octopus_DB::singleton();

		$tx = $db->beginTransaction();
		$tx->commit();

		$tx = $db->beginTransaction();

	}

	/**
	 */
	function testBeginTransactionWithRolledBackTxDoesNotThrowException() {

		$db = Octopus_DB::singleton();

		$tx = $db->beginTransaction();
		$tx->rollback();

		$tx = $db->beginTransaction();

	}

	function testGetTransaction() {

		$db = Octopus_DB::singleton();
		$this->assertNull($db->getTransaction(), 'no transaction running initially');

		$tx = $db->beginTransaction();
		$this->assertSame($tx, $db->getTransaction(), 'beginTransaction updates getTransaction');

		$tx->commit();
		$this->assertNull($db->getTransaction(), 'getTransaction() returns null after commit');

		$tx = $db->beginTransaction();
		$this->assertSame($tx, $db->getTransaction(), 'beginTransaction updates getTransaction');
		$tx->rollback();

		$this->assertNull($db->getTransaction(), 'getTransaction() returns null after rollback');
	}

	function testExceptionInCallbackRollsBack() {

		$db = Octopus_DB::singleton();
		$caught = false;

		try {

			$db->runTransaction(array($this, 'callbackThatThrowsAnException'));

		} catch(TransactionRollbackTestException $ex) {
			$caught = true;
		}

		$this->assertTrue($caught, 'Test exception was thrown');
	}

	function callbackThatThrowsAnException(Octopus_DB_Transaction $tx, Octopus_DB $db) {

		$i = new Octopus_DB_Insert();
		$i->table('transaction_test_table');
		$i->set('name', 'foo');
		$i->execute();

		throw new TransactionRollbackTestException("This exception should result in the transaction being rolled back");

	}

}