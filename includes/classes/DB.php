<?php

Octopus::loadClass('Octopus_DB_Result');
Octopus::loadClass('Octopus_DB_Error');

if (class_exists('PDO') && !defined('NO_PDO')) {
    Octopus::loadClass('Octopus_DB_Driver_Pdo');
} else {
    Octopus::loadClass('Octopus_DB_Driver_Mysql');
}

class Octopus_DB extends Octopus_Base {

    protected function Octopus_DB() {

        if (class_exists('PDO') && !defined('NO_PDO')) {
            $this->driver = new Octopus_DB_Driver_Pdo();
        } else {
            $this->driver = new Octopus_DB_Driver_Mysql();
        }

        $this->handle = null;
        $this->queries = array();
    }

    /**
     * @return Octopus_DB
     */
    public static function &singleton() {

        $obj =& Octopus_Base::base_singleton(get_class());

        if ($obj->driver->handle === null) {
            $obj->driver->connect();
        }

        return $obj;

    }

    function getFileCall() {

        $bt = debug_backtrace();

        foreach ($bt as $t) {

            $shortfile = str_replace(PUBLIC_ROOT_DIR, '', $t['file']);

            if (strstr($shortfile, 'DB') === false) {
                return $shortfile . ':' . $t['line'];
            }

        }

        return 'unknown';
    }

    function query($sql, $safe = false, $params = array()) {

        if (defined('DB_LOG_QUERIES')) {
            if (!$safe) {

                $loc = $this->getFileCall();

                $log = new Octopus_Logger_File(LOG_DIR . 'app.txt');
                $log->log(sprintf('UNSAFE SQL: %s %s', $loc, $sql));

                $sql = '/* UNSAFE */ ' . $sql;

            }
            $this->queries[] = $sql;
        }

        $query = $this->driver->query($sql, $params);

        if ($this->driver->success) {
            $result = new Octopus_DB_Result($this->driver, $query);
        } else {
            $result = new Octopus_DB_Error($this->driver->getError($query), $sql);
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
