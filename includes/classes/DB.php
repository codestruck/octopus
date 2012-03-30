<?php

class Octopus_DB extends Octopus_Base {

    public $queryCount = 0;

    private $_inTransaction = false;

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
     * Starts a new transaction.
     */
    public function beginTransaction() {

    	if ($this->_inTransaction) {
    		throw new Octopus_DB_Exception("A transaction has already been started. You need to commit it or roll it back to start a new one.");
    	}

    	$this->_inTransaction = true;
    	$this->driver->beginTransaction();
    }

    /**
     * Commits a
     */
    public function commitTransaction() {

    	if (!$this->_inTransaction) {
    		throw new Octopus_DB_Exception("No transaction has been started. Call beginTransaction() before calling commitTransaction() or rollbackTransaction()");
    	}

    	$this->driver->commitTransaction();
    	$this->_inTransaction = false;
    }

    /**
     * @return Bool Whether ::beginTransaction() has been called, but neither
     * ::commitTransaction() or ::rollbackTransaction() have been called to
     * finalize it.
     */
    public function inTransaction() {
    	return $this->_inTransaction;
    }

    public function rollbackTransaction() {

    	if (!$this->_inTransaction) {
    		throw new Octopus_DB_Exception("No transaction has been started. Call beginTransaction() before calling commitTransaction() or rollbackTransaction()");
    	}

    	$this->driver->rollbackTransaction();
    	$this->_inTransaction = false;
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

        $bt = debug_backtrace();

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

        if (defined('DB_LOG_QUERIES')) {

            if (1 || !$safe) {

                $loc = $this->getFileCall();
                if (defined('LOG_DIR')) {
                    $log = new Octopus_Logger_File(LOG_DIR . 'app.txt');
                    $log->log(sprintf('UNSAFE SQL: %s %s', $loc, $sql));
                }

                $sql = '/* UNSAFE */ ' . $sql;

            }
            $this->queries[] = $sql;
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


?>
