<?php

/**
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_DB extends Octopus_Base {

    public $queryCount = 0;

    protected function __construct() {

        if (class_exists('PDO') && !defined('NO_PDO')) {
            $this->driver = new Octopus_DB_Driver_Pdo();
        } else {
            $this->driver = new Octopus_DB_Driver_Mysql();
        }

        $this->handle = null;
        $this->queries = array();
    }

    /**
     * Starts a new transaction. Only one transaction can be running at a
     * time.
     * @return Octopus_DB_Transaction
     * @throws Octopus_DB_Exception If another transaction can be started.
     */
    public function beginTransaction() {

        if (Octopus_DB_Transaction::getCurrent()) {
            throw new Octopus_DB_Exception("A transaction has already been started. You need to commit it or roll it back to start a new one.");
        }

        return new Octopus_DB_Transaction($this, $this->driver);
    }

    /**
     * @return Mixed If an uncommitted transaction has been started, returns
     * it. Otherwise, returns null.
     */
    public function getTransaction() {
        return Octopus_DB_Transaction::getCurrent();
    }

    /**
     * Begins a new transaction and executes $callback. If $callback does not
     * commit or roll back the transaction, it is committed automatically.
     * If $callback throws an exception, the transaction is rolled back
     * before the exception is rethrown.
     * @param  function $callback Callback function to execute inside the
     * transaction. Will be passed 2 arguments: An Octopus_DB_Transaction
     * instance and this Octopus_DB instance. So the signature is:
     *
     *     function(Octopus_DB_Transaction $tx, Octopus_DB $db)
     * @param $retval Gets set to the value returned from $callback
     * @return boolean True if the transaction is committed, false otherwise.
     */
    public function runTransaction($callback, &$retval = null) {

        $tx = null;
        $retval = null;

        try {

            $tx = $this->beginTransaction();
            $retval = call_user_func($callback, $tx, $this);

            if ($tx->canCommit()) {
                $tx->commit();
            }

            return $tx->isCommitted();

        } catch(Exception $ex) {

            if ($tx && $tx->canRollBack()) {
                $tx->rollback();
            }

            throw $ex;

        }

    }

    /**
     * @return Octopus_DB
     */
    public static function &singleton() {

        $obj = Octopus_Base::base_singleton(get_class());

        if ($obj->driver->handle === null) {
            $obj->driver->connect();
        }

        return $obj;

    }

    function getFileCall() {

        $bt = debug_backtrace(false);

        foreach ($bt as $t) {

            if (defined('ROOT_DIR')) {
                $shortfile = str_replace(ROOT_DIR, '', $t['file']);
            }


            if (strstr($shortfile, 'DB') === false) {
                return $shortfile . ':' . $t['line'];
            }

        }

        return 'unknown';
    }

    function query($sql, $safe = false, $params = array()) {

        if (!$safe) {
            $message = 'Unsafe SQL';
            Octopus_Log::debug('db', compact('message', 'sql'));
        }

        $query = $this->driver->query($sql, $params);
        $this->queryCount++;

        if ($this->driver->success) {
            $result = new Octopus_DB_Result($this->driver, $query);
        } else {
            $result = new Octopus_DB_Error($this->driver->getError($query), $sql, $params);
        }

        return $result;
    }

    function limitQuery($sql, $from = null, $count = null, $dbparams = null) {

        if ($from !== null) {
            $sql .= " LIMIT $from ";
        }

        if ($count !== null) {
            $sql .= " , $count ";
        }

        $query = $this->query($sql, true, $dbparams);
        return $query;
    }

    function getOne($sql, $safe = false, $params = array()) {
        $query = $this->query($sql, $safe, $params);
        $result = $query->fetchRow();
        if (is_array($result)) {
            return array_shift($result);
        }
        return false;
    }

}


