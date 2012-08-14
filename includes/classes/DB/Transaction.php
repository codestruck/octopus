<?php

/**
 * Class representing a database transaction. Only one transaction can be
 * running at a time.
 * @see Octopus_DB::beginTransaction
 * @see Octopus_DB::runTransaction
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_DB_Transaction {

    /**
     * The uncommitted transaction state.
     */
    const UNCOMMITTED = 0;

    /**
     * The committed transaction state.
     * @see isCommitted
     * @see commit
     */
    const COMMITTED = 1;

    /**
     * The rolled back transaction state.
     * @see isRolledBack
     * @see rollback
     */
    const ROLLED_BACK = 2;

    private static $current = null;

    private $state = self::UNCOMMITTED;
    private $db;
    private $driver;

    /**
     * For internal use. To start a new transaction, use
     * Octopus_DB::beginTransaction.
     */
    public function __construct(Octopus_DB $db, $driver) {

        $this->db = $db;
        $this->driver = $driver;

        if (!self::$current) {
            self::$current = $this;
        }

        $this->driver->beginTransaction();
    }

    /**
     * @return Boolean Whether ::commit can currently be called. After calling
     * ::commit or ::rollback, subsequent calls to ::canCommit will return
     * false.
     */
    public function canCommit() {
        return $this->state === self::UNCOMMITTED;
    }

    /**
     * @return Boolean Whether ::rollback can currently be called. After calling
     * ::commit or ::rollback, subsequent calls to ::canRollBack will return
     * false.
     */
    public function canRollBack() {
        return $this->state === self::UNCOMMITTED;
    }

    /**
     * @return boolean If ::commit has been called.
     */
    public function isCommitted() {
        return $this->state === self::COMMITTED;
    }

    /**
     * @return boolean If ::rollback has been called.
     */
    public function isRolledBack() {
        return $this->state === self::ROLLED_BACK;
    }

    /**
     * Commits any changes that have happened since this transaction began.
     * Once a transaction is committed, it is no longer valid-- subsequent
     * calls to ::commit or ::rollback will throw exceptions.
     * @throws Octopus_DB_Exception If this transaction has already been
     * committed or rolled back.
     */
    public function commit() {

        $this->checkState();
        $this->makeInactive(self::COMMITTED);
        $this->driver->commitTransaction();

    }

    /**
     * Undoes any changes that have happened since this transaction began.
     * Once a transaction is rolled back, it is no longer valid-- subsequent
     * calls to ::commit or ::rollback will throw exceptions.
     * @throws Octopus_DB_Exception If this transaction has already been
     * committed or rolled back.
     */
    public function rollback() {

        $this->checkState();
        $this->makeInactive(self::ROLLED_BACK);
        $this->driver->rollbackTransaction();

    }

    private function checkState($desiredState = self::UNCOMMITTED) {

        if ($this->state === $desiredState) {
            return;
        }

        if ($this->state === self::COMMITTED) {
            throw new Octopus_DB_Exception("The transaction has already been committed, and is now invalid. Use Octopus_DB::beginTransaction() to start a new transaction.");
        } else if ($this->state === self::ROLLED_BACK) {
            throw new Octopus_DB_Exception("The transaction has already been rolled back, and is now invalid. Use Octopus_DB::beginTransaction() to start a new transaction.");
        }

    }

    private function makeInactive($newState) {
        $this->state = $newState;
        if (self::$current === $this) {
            self::$current = null;
        }
    }

    /**
     * @return null|Octopus_DB_Transaction The currently running transaction, if
     * any.
     */
    public static function getCurrent() {
        return self::$current;
    }

}