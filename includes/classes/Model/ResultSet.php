<?php

/**
 * Class that handles searching for SG_Model instances.
 */
class SG_Model_ResultSet implements Iterator {

    private $_parent;
    private $_modelClass;
    private $_criteria;
    private $_orderBy;
    private $_select;
    private $_query;
    private $_current = null;

    /**
     * Map of magic method name patterns to handler functions.
     */
    private static $_magicMethods = array(

        /**
         * e.g., for whereActive() and whereNotActive()
         */
        '/^where(?P<not>Not)?(?P<field>[A-Z][a-zA-Z0-9_]*)$/' => '_whereBoolean'

    );

    /**
     * Creates a new ResultSet for the given model class.
     */
    public function __construct($parent, $criteria = null, $orderBy = null) {

        if (is_string($parent)) {
            $this->_parent = null;
            $this->_modelClass = $parent;
        } else {
            $this->_parent = $parent;
            $this->_modelClass = $parent->_modelClass;
        }

        $this->_criteria = $criteria ? $criteria : array();
        $this->_orderBy = $orderBy ? $orderBy : array();

    }

    /**
     * @return Object A new ResultSet with extra constraints added via AND.
     */
    public function &and_(/* Variable */) {
        $args = func_get_args();
        $derivedSet = $this->_restrict('AND', $args);
        return $derivedSet;
    }

    /**
     * @return Number The # of records in this ResultSet.
     */
    public function count() {
        return $this->_query()->numRows();
    }

    /**
     * Sends the SQL for the current query to dump_r().
     * @return $this to continue the chain.
     */
    public function &dumpSql($normalize = true) {

        $params = array();
        $sql = $this->getSql($params);
        if ($normalize) $sql = normalize_sql($sql, $params);

        dump_r($sql);
        return $this;
    }

    /**
     * @return Mixed the first result matched, or false if none were matched.
     */
    public function &first() {

        $q = $this->_query(true);
        $row = $q->fetchRow();
        if (!$row) return $row;

        return $this->_createModelInstance($row);
    }

    /**
     * @param $params Array array into which to put the parameters.
     * @return string The SQL for the current query.
     */
    public function getSql(&$params = null) {

        $s = $this->_buildSelect();
        $sql = $s->getSql();

        if ($params !== null) {
            foreach($s->params as $p) {
                $params[] = $p;
            }
        }

        return $sql;
    }

    /**
     * @return Object A new ResultSet with extra constraints added via OR.
     */
    public function &or_(/* Variable */) {
        $args = func_get_args();
        $derivedSet = $this->_restrict('OR', $args);
        return $derivedSet;
    }

    /**
     * @return Object A new ResultSet sorted by the given arguments.
     */
    public function &orderBy(/* Variable */) {

        $args = func_get_args();
        $newOrderBy = array();

        foreach($args as $arg) {
            $this->_processOrderByArg($arg, $newOrderBy);
        }

        if (empty($newOrderBy) && empty($this->_orderBy)) {
            // Don't create new objects when we don't have to
            return $this;
        }

        $derivedSet = new SG_Model_ResultSet($this, null, $newOrderBy);
        return $derivedSet;
    }

    /**
     * Adds additional criteria to the resultset, filtering whatever is
     * presently in there.
     */
    public function &where(/* Variable */) {
        $args = func_get_args();
        $rs = $this->_restrict('AND', $args);
        return $rs;
    }

    private function _applyOrderByClause(&$s, &$orderBy) {

        if (empty($orderBy)) {
            return;
        }

        foreach($orderBy as $fieldName => $dir) {

            if (!is_string($dir)) {
                $dir = $dir ? 'ASC' : 'DESC';
            } else {
                $dir = strtoupper($dir);
            }

            $field = $this->_getField($fieldName);
            $field->orderBy($s, $dir);
        }

    }

    /**
     * Takes a big fat criteria array and generates a WHERE clause.
     * @param $criteria Array Set of criteria to compile into a WHERE clause.
     * @param $s Object SG_DB_Select being used.
     * @param $sql String Variable to hold generated SQL.
     * @param $params Array Set of parameters referenced by $sql.
     */
    private function _generateWhereClause(&$criteria, &$s, &$sql, &$params, $processParent = false) {

        if ($processParent && $this->_parent) {
            $this->_parent->_generateWhereClause($this->_parent->_criteria, $s, $sql, $params, true);
        }

        if (empty($criteria)) {
            return;
        }

        $lastFieldName = null;
        $conjunction = 'AND';

        foreach($criteria as $key => $value) {

            $criteriaSql = '';

            if (is_numeric($key)) {

                // This is probably something w/o an explicit key set.

                if (is_array($value)) {

                    // Handle ('Id In', array(1,2,3,4)
                    if ($lastFieldName) {
                        $c = array($lastFieldName => $value);
                        $this->_generateWhereClause($c, $s, $criteriaSql, $params);
                    } else {
                        // Process as an array of criteria
                        $this->_generateWhereClause($value, $s, $criteriaSql, $params);
                    }

                    $lastFieldName = null;

                } else if (is_string($value)) {

                    // Could be 'AND' or 'OR'
                    if (strcasecmp($value, 'or') == 0 || strcasecmp($value, 'and') == 0) {
                        $conjunction = strtoupper($value);
                        $lastFieldName = null;
                    } else {
                        // Handle ('field name', 'value')
                        if ($lastFieldName) {
                            $c = array($lastFieldName => $value);
                            $this->_generateWhereClause($c, $s, $criteriaSql, $params);
                            $lastFieldName = null;
                        } else {
                            $lastFieldName = $value;
                        }
                    }
                }

            } else {

                // standard field = whatever syntax
                self::_readCriteriaKey($key, $fieldName, $operator);


                // HACK: special-case id
                if (strcasecmp($fieldName, 'id') == 0) {
                    $mc = $this->_modelClass;
                    $fieldName = $mc::getPrimaryKey();
                    $criteriaSql = SG_Model_Field::defaultRestrict($mc::getPrimaryKey(), $operator, '=', $value, $s, $params);
                } else {
                    $field = $this->_getField($fieldName);

                    if (!$field) {
                        //dump_r("Field not found: $fieldName");
                        continue;
                    }

                    $criteriaSql = $field->restrict($operator, $value, $s, $params);
                }
            }

            if (!empty($criteriaSql)) {

                if ($conjunction) {

                    if (strlen($sql)) {
                        $sql .= "$conjunction ";
                    }

                    $conjunction = 'AND';
                }

                $sql .= $criteriaSql . ' ';
            }
        }
    }

    private function &_getField($name) {
        $modelClass = $this->_modelClass;
        $field = $modelClass::getField($name);
        return $field;
    }

    /**
     * Takes a key from a criteria array and parses it out into field name
     * and operator.
     */
    private function _readCriteriaKey($key, &$fieldName, &$operator) {

        $fieldName = $operator = null;

        $spacePos = strpos($key, ' ');
        if ($spacePos !== false) {
            $fieldName = substr($key, 0, $spacePos);
            $operator = substr($key, $spacePos + 1);
        } else {
            $fieldName = $key;
        }

        $fieldName = trim($fieldName, '`');
    }

    /**
     * @return Object A new SG_DB_Select instance.
     */
    private function &_buildSelect($recreate = false) {

        if (!$recreate && $this->_select) {
            return $this->_select;
        }

        $modelClass = $this->_modelClass;

        $s = new SG_DB_Select();
        $s->table($modelClass::getTableName());

        $sql = '';
        $params = array();
        $this->_generateWhereClause($this->_criteria, $s, $sql, $params, true);

        if (strlen($sql) > 0) {
            $s->where($sql, $params);
        }

        $this->_applyOrderByClause($s, $this->_orderBy);

        $this->_select = $s;
        return $this->_select;
    }

    /**
     * Factory method used to generate an instance of SG_Model from the given
     * row of data.
     * @return Object A new model instance from the given row.
     */
    protected function &_createModelInstance(&$row) {
        $class = $this->_modelClass;
        $instance = new $class($row);
        return $instance;
    }

    /**
     * Runs the backing query and
     */
    private function &_query($new = false) {

        if ($this->_query && !$new) {
            return $this->_query;
        }

        $s = $this->_buildSelect();
        $this->_query = $s->query();

        return $this->_query;
    }

    /**
     * Handles a single 'order by' argument. This could be a string (e.g.
     * 'whatever DESC') or an array (e.g. array('whatever' => 'DESC') ).
     * @return boolean TRUE if something is made of the argument, FALSE otherwise.
     */
    private function _processOrderByArg($arg, &$orderBy) {

        if (is_string($arg)) {

            $parts = explode(' ', $arg);
            $count = count($parts);

            if ($count == 1) {

                // default = column ASC
                $orderBy[$parts[0]] = 'ASC';
                return true;

            } else if ($count > 1) {

                $dir = strtoupper(array_pop($parts));
                $orderBy[$count == 2 ? $parts[0] : implode(' ', $parts)] = $dir;
                return true;

            }

            return false;
        }

        if (is_array($arg)) {

            $processed = 0;
            foreach($arg as $field => $dir) {

                if (is_numeric($field)) {
                    // this is an entry at a numeric index, e.g.
                    // ------vvvvvv-----------------------------
                    // array('name', 'created' => 'desc')
                    if ($this->_processOrderByArg(array($dir => 'ASC'), $orderBy)) {
                        $processed++;
                        continue;
                    }
                }

                if (!is_string($dir)) {
                    $dir = ($dir ? 'ASC' : 'DESC');
                } else {
                    $dir = strtoupper($dir);
                }

                $orderBy[$field] = $dir;
                $processed++;
            }

            return ($processed > 0);
        }

        return false;
    }

    /**
     * Internal handler for and() and or().
     */
    private function &_restrict($operator, $args) {

        if (empty($args)) {
            return $this;
        }

        array_unshift($args, $operator);

        $derivedSet = new SG_Model_ResultSet($this, $args, $this->_orderBy);
        return $derivedSet;
    }

    /**
     * Handles the where____ magic method for boolean fields.
     * @param $matches Array set of matched groups from the magic method pattern.
     */
    private function &_whereBoolean($matches) {

        $field = underscore($matches['field']);
        $not = isset($matches['not']) ? (strcasecmp('not', $matches['not']) == 0) : false;

        $derivedSet = $this->_restrict('AND', array($field => $not ? 0 : 1));
        return $derivedSet;
    }

    public function __call($name, $args) {

        foreach(self::$_magicMethods as $pattern => $handler) {
            if (preg_match($pattern, $name, $m)) {
                return $this->$handler($m);
            }
        }

    }

    // Iterator Implementation {{{

    public function current() {

        if ($this->_current) {
            return $this->_current;
        }

        $row = $this->_query()->fetchRow();
        if (!$row) return false;

        $this->_current = $this->_createModelInstance($row);
        return $this->_current;
    }

    public function key() {
        if (!$this->_current) {
            return false;
        }
        return $this->_current->id;
    }

    public function next() {
        $this->_current = null;
    }

    public function rewind() {
        $this->_current = null;
        $this->_query = null;
    }

    public function valid() {
        return $this->_current !== null;
    }


    // }}}


}


?>
