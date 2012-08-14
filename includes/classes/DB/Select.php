<?php
/**
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_DB_Select extends Octopus_DB_Helper {

    function __construct($sql = null, $params = array()) {
        parent::__construct($sql, $params);
        $this->joins = array();
        $this->joinFields = array();
        $this->orderBy = array();
        $this->groupBy = array();
        $this->having = null;
        $this->funcs = array();
        $this->havingParams = array();

        $this->db->debugBacktraceLevel = 2;
    }

    /**
     * @return Octopus_DB_Select A DB_Select that you can call getOne() on
     * to return the number of rows matched by this select.
     * This is public for testing purposes.
     */
    public function createCountSelect() {

        $s = clone($this);

        foreach($s->tables as $table => $fields) {
            $s->tables[$table] = array();
        }

        $s->runFunction('COUNT', '', '*');
        $s->joinFields = array();

        return $s;
    }

    /**
     * @return The number of rows matched by this select, taking into a
     */
    public function numRows($useLimit = true) {

        $s = $this->createCountSelect();
        $count = $s->getOne();
        if (!$count) $count = 0;

        if (!$useLimit) {
        	return $count;
        }

        if ($this->limitStart > 0) {
        	$count = $count - $this->limitStart;
        }

        if ($this->limitLen !== null) {
        	$count = min($this->limitLen, $count);
        }

        return max(0, $count);
    }

    /**
     * Set table name, table alias and fields to select
     *
     * @param array|string $table string TABLE_NAME -or- array(TABLE_NAME, TABLE_ALIAS)
     * @param array|string $fields sting FIELD_TO_SELECT -or- array(FIELD_TO_SELECT, OTHER_FIELD)
     */
    function table($table, $fields = null) {

        if (null === $fields) {
            $fields = null;
        }

        if (!is_array($fields) && $fields !== null) {
            $fields = array($fields);
        }

        if (is_array($table) && count($table) === 1) {
            $table = array_shift($table);
        }

        if (is_array($table) && count($table) === 2) {
            $alias = array_pop($table);
            $table = array_shift($table);
            $this->aliases[$table] = $alias;
        }

        $this->tables[$table] = $fields;

    }

    /**
     * @access private
     */
    function join($type, $table, $on, $fields = null) {

        if ($type) {
            $type .= ' ';
        }

        $tableLine = $table;
        $joinFieldTable = $table;

        if (is_array($table) && count($table) === 2) {
            $alias = array_pop($table);
            $table = array_shift($table);
            $tableLine = "$table AS $alias";
            $joinFieldTable = $alias;
        }

        if (is_array($fields)) {
            foreach ($fields as $field) {
                $this->joinFields[] = array($joinFieldTable, $field);
            }
        } else {
            $this->joinFields[] = array($joinFieldTable, '*');
        }

        // allow $on to be array of just the one USING arg
        if (is_array($on) && count($on) === 1) {
            $on = array_shift($on);
        }

        // if $on is array of 2 it's ON what = what, else it's a USING (what) query
        if (is_array($on) && count($on) == 2) {
            $this->joins[] = sprintf('%sJOIN %s ON %s = %s', $type, $tableLine, $on[0], $on[1]);
        } else {
            $this->joins[] = sprintf('%sJOIN %s USING (%s)', $type, $tableLine, $on);
        }
    }

    /**
     * Create LEFT JOIN
     *
     * @param array|string $table string TABLE_NAME -or- array(TABLE_NAME, TABLE_ALIAS)
     * @param array|string $on string field to join USING -or- array of 2 fields to join ON
     * @param array|string $fields sting FIELD_TO_SELECT -or- array(FIELD_TO_SELECT, OTHER_FIELD)
     */
    function leftJoin($table, $on, $fields = null) {
        $this->join('LEFT', $table, $on, $fields);
    }

    /**
     * Create RIGHT JOIN
     *
     * @param array|string $table string TABLE_NAME -or- array(TABLE_NAME, TABLE_ALIAS)
     * @param array|string $on string field to join USING -or- array of 2 fields to join ON
     * @param array|string $fields sting FIELD_TO_SELECT -or- array(FIELD_TO_SELECT, OTHER_FIELD)
     */
    function rightJoin($table, $on, $fields = null) {
        $this->join('RIGHT', $table, $on, $fields);
    }

    /**
     * Create INNER JOIN
     *
     * @param array|string $table string TABLE_NAME -or- array(TABLE_NAME, TABLE_ALIAS)
     * @param array|string $on string field to join USING -or- array of 2 fields to join ON
     * @param array|string $fields sting FIELD_TO_SELECT -or- array(FIELD_TO_SELECT, OTHER_FIELD)
     */
    function innerJoin($table, $on, $fields = null) {
        $this->join('INNER', $table, $on, $fields);
    }

    function orderBy($cond) {
        $this->orderBy[] = $cond;
    }

    function groupBy($cond) {
        $this->groupBy[] = $cond;
    }

    function having($cond, $args = null) {

        if (is_array($args)) {
            $this->havingParams = array_merge($this->havingParams, $args);
        } else if ($args) {
            $this->havingParams[] = $args;
        }

        $this->having = $cond;
    }

    function limit($start, $len = null) {
        $this->limitStart = $start;
        $this->limitLen = $len;
    }

    function distinct($table, $field) {
        // TODO: can a table have more than one DISTINCT field?
        $this->distinct[$table] = $field;
    }

    function runFunction($func, $table, $field) {

        if (!isset($this->funcs[$func])) {
            $this->funcs[$func] = array();
        }

        if (!isset($this->funcs[$func][$table])) {
            $this->funcs[$func][$table] = array();
        }

        $this->funcs[$func][$table] = $field;
    }


    function fetchRow() {
        $query = $this->query();
        $result = $query->fetchRow();
        return $result;
    }

    function fetchObject() {
        $query = $this->query();
        $result = $query->fetchObject();
        return $result;
    }

    function fetchAll() {
        $query = $this->query();
        return $query->fetchAll();
    }

    function getOne() {

        $sql = $this->getSql();
        $sql = implode('', $this->comments) . $sql;
        $result = $this->db->getOne($sql, true, $this->params);

        return $result;

    }

    function getOneArray() {

        $data = array();
        $query = $this->query();

        while ($result = $query->fetchRow()) {
            $data[] = array_shift($result);
        }

        return $data;

    }

    function getMap() {

        $data = array();
        $query = $this->query();

        while ($result = $query->fetchRow()) {
            $key = array_shift($result);
            $data[$key] = array_pop($result);
        }

        return $data;

    }

    function getSql() {

        if ($this->sql) {
            $this->passParams = $this->params;
            return $this->sql;
        }

        $this->passParams = array();

        $sql = 'SELECT ';

        $tableList = array();
        $fieldList = array();

        $multiTable = (count($this->tables) > 1) || (count($this->joinFields) > 0) || (count($this->joins) > 0);

        foreach ($this->tables as $table => $fields) {

            if (isset($this->aliases[$table])) {
                $tableList[] = $table . ' AS ' . $this->aliases[$table];
            } else {
                $tableList[] = $table;
            }


            if (is_array($fields) && count($fields) > 0) {

                foreach ($fields as $field) {

                    $fl = '';

                    if ($multiTable) {

                        $fieldTable = $table;

                        if (isset($this->aliases[$table])) {
                            $fieldTable = $this->aliases[$table];
                        }

                        $fl .= $fieldTable . '.';
                    }

                    $fl .= $field;

                    if (isset($this->distinct[$table]) && $this->distinct[$table] == $field) {
                        $fl = "DISTINCT($fl)";
                    }

                    foreach (array_keys($this->funcs) as $func) {

                        if (isset($this->funcs[$func]) && isset($this->funcs[$func][$table])) {

                            $functionField = $this->funcs[$func][$table];
                            $alias = '';
                            if (is_array($this->funcs[$func][$table])) {
                                $functionField = $this->funcs[$func][$table][0];
                                $alias = ' AS ' . $this->funcs[$func][$table][1];
                            }

                            if ($functionField == $field) {
                                $fl = strtoupper($func) . "($fl)" . $alias;
                            }
                        }
                    }

                    $fieldList[] = $fl;
                }

            } else if (!is_array($fields)) {
                $fl = '';

                if ($multiTable) {
                    if (isset($this->aliases[$table])) {
                        $fl .= $this->aliases[$table] . '.';
                    } else {
                        $fl .= $table . '.';
                    }
                }

                $fl .= '*';
                $fieldList[] = $fl;
            }
        }

        // Handle functions not run against any table, e.g. COUNT(*)
        foreach($this->funcs as $func => $tables) {
            foreach($tables as $table => $fields) {
                if ($table === '') {

                    if (is_array($fields)) {
                        $field = $fields[0];
                        $alias = $fields[1];
                    } else {
                        $field = $fields;
                        $alias = '';
                    }

                    if ($alias) {
                        $alias = " AS $alias";
                    }

                    $fl = strtoupper($func) . "($field){$alias}";
                    $fieldList[] = $fl;
                }
            }
        }


        foreach ($this->joinFields as $info) {
            list($table, $field) = $info;

            $fl = $table . '.' . $field;

            foreach (array_keys($this->funcs) as $func) {

                if (isset($this->funcs[$func]) && isset($this->funcs[$func][$table])) {

                    $functionField = $this->funcs[$func][$table];
                    $alias = '';
                    if (is_array($this->funcs[$func][$table])) {
                        $functionField = $this->funcs[$func][$table][0];
                        $alias = ' AS ' . $this->funcs[$func][$table][1];
                    }

                    if ($functionField == $field) {
                        $fl = strtoupper($func) . "($fl)" . $alias;
                    }
                }
            }

            $fieldList[] = $fl;
        }

//        $fieldList = array_merge($fieldList, $this->joinFields);

        $sql .= implode(', ', $fieldList);
        $sql .= ' FROM ';
        $sql .= implode(', ', $tableList);

        if (count($this->joins)) {
            $sql .= ' ' . implode(' ', $this->joins);
        }

        $sql .= $this->_buildWhere();

        if (count($this->groupBy) > 0) {
            $groupBy = implode(', ', $this->groupBy);
            $sql .= ' GROUP BY ' . $groupBy;
        }

        if ($this->having) {
            $sql .= ' HAVING ' . $this->having;
            $this->passParams = array_merge($this->passParams, $this->havingParams);
        }

        if (count($this->orderBy) > 0) {
            $orderBy = implode(', ', $this->orderBy);
            $sql .= ' ORDER BY ' . $orderBy;
        }

        if ($this->limitStart !== null) {
            $sql .= ' LIMIT ' . $this->limitStart;

            if ($this->limitLen !== null) {
                $sql .= ', ' . $this->limitLen;
            }
        }

        return $sql;
    }

}

