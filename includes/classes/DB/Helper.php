<?php

define_unless('SQL_DATE_FORMAT', 'Y-m-d H:i:s');
/**
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_DB_Helper {

    function __construct($sql = null, $params = array()) {

        $this->sql = $sql;
        $this->params = $params;
        $this->passParams = array();

        $this->tables = array();
        $this->where = array();
        $this->distinct = array();
        $this->limitStart = null;
        $this->limitLen = null;
        $this->comments = array();
        $this->table = null;
        $this->where = array();
        $this->values = array();
        $this->rawValues = array();

        $this->db = Octopus_DB::singleton();

    }

    /**
     * Add a sql comment for debugging
     *
     * @param string $comment
     */
    function comment($comment) {
        $this->comments[] = '/* ' . $comment . ' */ ';
    }

    function table($table, $fields = null) {
        $this->table = $table;
    }

    function set($key, $value) {

        $this->values[$key] = $value;

    }

    function setRaw($key, $value) {

        $this->rawValues[$key] = $value;

    }

    function setNow($key) {
        $this->values[$key] = date(SQL_DATE_FORMAT);
    }

    function where($cond, $arg = null) {

        $args = func_num_args();

        if ($args > 1) {

            for ($i = 1; $i < $args; ++$i) {

                $arg = func_get_arg($i);
                if (is_array($arg)) {
                    foreach ($arg as $value) {
                        $this->params[] = $value;
                    }
                } else {
                    $this->params[] = $arg;
                }
            }

        }

        $this->where[] = $cond;

    }

    /**
     * Alias for @see query().
     */
    public function execute() {
        return $this->query();
    }

    /**
     * Executes the SQL contained in this command.
     * @throws Octopus_DB_Exception
     */
    public function query() {

        $sql = $this->getSql();

        $sql = implode('', $this->comments) . $sql;

        $query = $this->db->query($sql, true, $this->passParams);

        if (!$query) {
            throw Octopus_DB_Exception::forSql($sql, $this->passParams);
        } else if ($query instanceof Octopus_DB_Error) {
            throw Octopus_DB_Exception::forSql($sql, $this->passParams, $query->error);
        }

        $this->passParams = array();
        $this->query = $query;

        return $query;
    }


    /**
     * For DELETE, UPDATE, and INSERT queries, returns the # of rows
     * affected the last time the query was executed.
     */
    function affectedRows() {
        return $this->db->driver->affectedRows($this->query->query);
    }

    function getId() {
        return $this->db->driver->getId();
    }

    function quote($text) {
        return $this->db->driver->quote($text);
    }

    function _buildWhere() {

        $this->passParams = array_merge($this->passParams, $this->params);

        if (count($this->where) > 0) {
            $where = implode(' AND ', $this->where);
            return ' WHERE ' . $where;
        }

        return '';
    }

    function _buildSet() {

        $sql = ' SET ';

        $sets = array();

        foreach ($this->values as $key => $value) {

            $setItem = "`$key` = ?";
            $sets[] = $setItem;
            $this->passParams[] = $value;
        }

        foreach ($this->rawValues as $key => $value) {
            $setItem = "`$key` = $value";
            $sets[] = $setItem;
        }

        $setQuery = implode(' , ', $sets);

        $sql .= $setQuery;

        return $sql;
    }
}

