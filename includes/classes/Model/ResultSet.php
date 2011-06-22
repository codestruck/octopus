<?php

/**
 * Class that handles searching for Octopus_Model instances.
 */
class Octopus_Model_ResultSet implements ArrayAccess, Countable, Iterator {

    public $escaped = false;

    private $_parent;
    private $_modelClass;
    private $_criteria;
    private $_orderBy;
    private $_select;
    private $_query;
    private $_currentQuery = null;
    private $_current = null;
    private $_arrayAccessResults = null;
    private $_empty;

    private $_offset = null;
    private $_maxRecords = null;

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
    public function __construct($parentOrModelClass, $criteria = null, $orderBy = null, $empty = false) {

        if (is_string($parentOrModelClass)) {
            $this->_parent = null;
            $this->_modelClass = $parentOrModelClass;
        } else {
            $this->_parent = $parentOrModelClass;
            $this->_modelClass = $this->_parent->_modelClass;
        }

        $this->_criteria = $criteria ? $criteria : array();
        $this->_orderBy = $orderBy ? $orderBy : array();
        $this->_empty = $empty;
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
     * @param $offset Number Record at which to start returning results.
     * @param $maxRecords Mixed Number of records to return. If null, all records are returned.
     * @return Octopus_ResultSet A result set that starts returning records
     * at $offset, and returns at most $maxRecords.
     */
    public function limit($offset, $maxRecords = null) {

        if ($offset === $this->_offset && $count === $this->_maxRecords) {
            return $this;
        }

        $result = new Octopus_Model_ResultSet($this, null, $this->_orderBy);
        $result->_offset = $offset;
        $result->_maxRecords = $maxRecords;
        return $result;

    }

    /**
     * @return Octopus_ResultSet A copy of this resultset with any limiting
     * restrictions removed.
     */
    public function unlimit() {

        if ($this->_offset === null && $this->_maxRecords === null) {
            return $this;
        }

        return new Octopus_Model_ResultSet($this, null, $this->_orderBy);
    }

    /**
     * @return String The model class this result set holds.
     */
    public function getModel() {
        return $this->_modelClass;
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
     * OR
     */
    public function also(/* variable */) {
        $args = func_get_args();
        return $this->_restrict('OR', $args);
    }

    /**
     * Restricts via free-text search.
     */
    public function matching($text) {
        return $this->where($this->createFreeTextCriteria($text));
    }

    protected function createFreeTextCriteria($text) {

        $class = $this->getModel();

        $dummy = new $class();
        $searchFields = null;
        $text = trim($text);
        if (!$text) return array();

        if (isset($dummy->search)) {
            $searchFields = is_array($dummy->search) ? $dummy->search : array($dummy->search);
        }

        if ($searchFields === null) {

            $searchFields = array();

            foreach($dummy->getFields() as $field) {

                $isText = $field->getOption('type', 'text') == 'string';

                if ($field->getOption('searchable', $isText)) {
                    $searchFields[] = $field;
                }
            }

        } else {

            $fields = array();
            foreach($searchFields as $name) {
                $field = $dummy->getField($name);
                if ($field) $fields[] = $field;
            }
            $searchFields = $fields;
        }

        $criteria = array();
        foreach($searchFields as $field) {

            if (count($criteria)) $criteria[] = 'OR';
            $criteria[$field->getFieldName() . ' LIKE'] = wildcardify($text);

        }

        return $criteria;

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

        $derivedSet = new Octopus_Model_ResultSet($this, null, $newOrderBy);
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

    /**
     * Deletes all results
     */
    public function &delete() {
        foreach ($this as $item) {
            $item->delete();
        }

        $this->_query = null;
        return $this;
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

            // Special-case id
            $pk = $this->getModelPrimaryKey();
            if ($fieldName == 'id' || $fieldName == $pk) {
                Octopus_Model_Field::defaultOrderBy($this, $s, $pk, $dir);
            }

            $field = $this->_getField($fieldName);
            if ($field) {
                $field->orderBy($this, $s, $dir);
            }
        }

    }

    private $_hackyModelInstance = null;
    private function getModelPrimaryKey() {

        // TODO Octopus_Model::getPrimaryKey() should be static

        if (!$this->_hackyModelInstance) {
            $class = $this->getModel();
            $this->_hackyModelInstance = new $class();
        }

        return $this->_hackyModelInstance->getPrimaryKey();
    }

    private static function paren($sql) {
        $sql = trim($sql);


        return $sql ? '(' . $sql . ')' : '';
    }

    private static function joinSql($conj, $left, $right) {

        $left = self::paren($left);
        $right = self::paren($right);

        if ($left && $right) {
            return $left . ' ' . $conj . ' ' . $right;
        } else if ($left) {
            return $left;
        } else if ($right) {
            return $right;
        } else {
            return '';
        }
    }

    /**
     * Takes a big fat criteria array and generates a WHERE clause.
     * @param $criteria Array Set of criteria to compile into a WHERE clause.
     * @param $s Object Octopus_DB_Select being used.
     * @param $sql String Variable to hold generated SQL.
     * @param $params Array Set of parameters referenced by $sql.
     */
    private function _generateWhereClause(&$criteria, &$s, &$sql, &$params, $processParent = false) {

        if ($processParent && $this->_parent) {
            $this->_parent->_generateWhereClause($this->_parent->_criteria, $s, $sql, $params, true);
        }

        $fragment = '';

        if ($this->_empty) {
            $fragment = '(1 = 0)';
        }

        if (empty($criteria)) {
            return;
        }

        if (is_string($criteria)) {
            $criteria = array($criteria);
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

                } else {

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
                        } else if (self::looksLikeFieldName($value)) {
                            $lastFieldName = $value;
                        } else {
                            // HACK: Add as literal SQL.
                            $criteriaSql = self::paren($value);
                        }
                    }
                }

            } else {

                // check if we are passed a result set
                if ($value instanceof Octopus_ResultSet) {
                    $newValue = array();

                    foreach ($value as $item) {
                        $newValue[] = $item->id;
                    }

                    $value = $newValue;
                } else if ($value instanceof Octopus_Model) {
                    $value = $value->id;
                }

                // standard field = whatever syntax
                self::_readCriteriaKey($key, $fieldName, $operator, $function);

                // TODO: handle functions

                // HACK: special-case id
                if (strcasecmp($fieldName, 'id') == 0) {
                    $mc = $this->_modelClass;
                    $obj = new $mc();
                    $fieldName = $obj->getPrimaryKey();
                    $criteriaSql = Octopus_Model_Field::defaultRestrict($fieldName, $operator, '=', $value, $s, $params, $obj);
                } else {
                    $field = $this->_getField($fieldName);

                    $mc = $this->_modelClass;
                    $obj = new $mc();

                    if ($field) {
                        $criteriaSql = $field->restrict($operator, $value, $s, $params, $obj);
                    } else {
                        $criteriaSql = Octopus_Model_Field::defaultRestrict($fieldName, $operator, '=', $value, $s, $params, $obj);
                    }
                }
            }

            $fragment = self::joinSql($conjunction, $fragment, $criteriaSql);
        }

        $sql = self::joinSql('AND', $sql, $fragment);

    }

    private static function looksLikeFieldName($candidate) {

        return preg_match('/^\s*[a-z0-9_]+\s*$/', $candidate);

    }

    private function &_getField($name) {
        $mc = $this->_modelClass;
        $obj = new $mc();
        $field = $obj->getField($name);
        return $field;
    }

    /**
     * Takes a key from a criteria array and parses it out into field name
     * and operator.
     */
    private function _readCriteriaKey($key, &$fieldName, &$operator, &$function) {

        $fieldName = $operator = $function = null;

        $key = trim($key);
        $key = str_replace('`', '', $key);
        $spacePos = strrpos($key, ' ');

        if ($spacePos !== false) {
            $fieldName = trim(substr($key, 0, $spacePos));
            $operator = substr($key, $spacePos + 1);
        } else {
            $fieldName = $key;
        }

        if (preg_match('/^([a-z0-9_]+)\((.+?)\)$/i', $fieldName, $m)) {
            $function = $m[1];
            $fieldName = $m[2];
        }
    }

    /**
     * @return Object A new Octopus_DB_Select instance.
     */
    private function &_buildSelect($recreate = false) {

        if (!$recreate && $this->_select) {
            return $this->_select;
        }

        $mc = $this->_modelClass;
        $obj = new $mc();

        $s = new Octopus_DB_Select();
        $s->table($obj->getTableName());

        $sql = '';
        $params = array();
        $this->_generateWhereClause($this->_criteria, $s, $sql, $params, true);

        if (strlen($sql) > 0) {
            $s->where($sql, $params);
        }

        $this->_applyOrderByClause($s, $this->_orderBy);

        if ($this->_offset !== null || $this->_maxRecords !== null) {
            $s->limit(($this->_offset === null ? 0 : $this->_offset), $this->_maxRecords);
        }

        $this->_select = $s;
        return $this->_select;
    }

    /**
     * Factory method used to generate an instance of Octopus_Model from the given
     * row of data.
     * @return Object A new model instance from the given row.
     */
    protected function &_createModelInstance(&$row) {

        //HACK: Because $row might contain extra fields (like from a join,
        // We have to use the public 'setData' (which only tries to set fields
        // that exist).

        $class = $this->_modelClass;
        $id = $row[$this->getModelPrimaryKey()];
        $instance = new $class($id);
        $instance->setData($row);
        $instance->escaped = $this->escaped;
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

        $derivedSet = new Octopus_Model_ResultSet($this, $args, $this->_orderBy);
        $derivedSet->escaped = $this->escaped;
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

        throw new Octopus_Exception("'$name' is not a valid method on Octopus_ResultSet");

    }

    // Iterator Implementation {{{

    public function current() {
        return $this->_current;
    }

    public function key() {
        if (!$this->_current) {
            return false;
        }
        return $this->_current->id;
    }

    public function next() {
    }

    public function rewind() {
        $this->_currentQuery = $this->_query(true);
    }

    public function valid() {

        if (!$this->_currentQuery) {
            $this->_current = null;
            return false;
        }

        $row = $this->_currentQuery->fetchRow();
        if (!$row) {
            $this->_current = null;
            $this->_currentQuery = null;
            return false;
        }

        $this->_current = $this->_createModelInstance($row);
        return true;
    }

    /**
     * @return Number The # of records in this ResultSet.
     */
    public function count() {
        return $this->_query()->numRows();
    }

    // }}}

    // ArrayAccess Implementation {{{

    private function getArrayAccessResult() {
        if (!$this->_arrayAccessResults) {
            $query = $this->_query(true);
            $this->_arrayAccessResults = $query->fetchAll();
        }

        return $this->_arrayAccessResults;
    }

    public function offsetExists($offset) {
        $all = $this->getArrayAccessResult();
        return isset($all[$offset]);
    }

    public function offsetGet($offset) {
        $all = $this->getArrayAccessResult();
        if (isset($all[$offset])) {
            return $this->_createModelInstance($all[$offset]);
        }

        return null;
    }

    public function offsetSet($offset, $value) {
        // no, you can't do this
        throw new Octopus_Model_Exception('You cannot set this, that does not make sense.');
    }

    public function offsetUnset($offset) {
        // no, you can't do this
        throw new Octopus_Model_Exception('You cannot unset this, that does not make sense.');
    }

    // }}}


}


?>
