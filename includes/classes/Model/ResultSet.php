<?php

/**
 * Class that handles searching for Octopus_Model instances.
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_Model_ResultSet implements ArrayAccess, Countable, Iterator, Dumpable, Octopus_DataSource {

    public $escaped = false;

    private $_parent;
    private $_conjunction = '';

    private $_modelClass;
    private $_modelInstance = null;

    private $_criteria;
    private $_orderBy = null;
    private $_comparatorFunction = null;
    private $_select;
    protected $query;

    private $_iteratorQuery = null;
    private $_iteratorItems = null;
    private $_iteratorIndex = 0;
    private $_iteratorCurrentItem = null;

    private $_itemArray = null;

    private $_offset = null;
    private $_maxRecords = null;

    /**
     * Map of magic method name patterns to handler functions.
     */
    private static $_magicMethods = array(

        /**
         * e.g., for whereActive() and whereNotActive()
         */
        '/^where(?P<not>Not)?(?P<field>[A-Z][a-zA-Z0-9_]*)$/' => 'whereBoolean'

    );

    /**
     * Creates a new result set.
     * @param $parentOrModelClass String|Octopus_Model_ResultSet Either another
     * Octopus_Model_ResultSet to build upon or the name of a model class for
     * which to create a new result set.
     * @param $criteria Mixed Criteria used to filter things in this result set.
     * @param $orderBy mixed Sorting to use for this result set.
     * @param $conjunction string If $parentOrModelClass is a result set,
     * this is the conjunction used to join the SQL in the parent to the SQL
     * for this result set.
     */
    public function __construct($parentOrModelClass, $criteria = null, $orderBy = null, $conjunction = 'AND') {

        if (is_string($parentOrModelClass)) {
            $this->_parent = null;
            $this->_modelClass = $parentOrModelClass;
        } else {
            $this->_parent = $parentOrModelClass;
            $this->_modelClass = $this->_parent->_modelClass;
        }

        $this->_criteria = $criteria ? $this->normalizeCriteria($criteria) : array();
        $this->_orderBy = $orderBy ? $orderBy : array();
        $this->_conjunction = $conjunction;

    }

    ////////////////////////////////////////
    //
    // Public Methods
    //
    ////////////////////////////////////////

    /**
     * Appends the contents of another result set to this result set and
     * returns the result. Maintains sorting of $this.
     */
    public function add(/* Variable */) {
        return $this->derive(func_get_args(), 'OR');
    }

    /**
     * @return bool Whether this resultset contains each item referenced in the
     * arguments.
     */
    public function contains(/* variable */) {

        $args = func_get_args();
        foreach($args as $arg) {

            if (!is_numeric($arg)) {
                $arg = $arg->id;
            }

            if (!$arg) {
                return false;
            }

            if (!$this->where(array('id' => $arg))->count()) {
                return false;
            }

        }

        return true;

    }

    /**
     * ResultSet implements Countable, so this:
     * 		count($resultSet)
     * is equivalent to:
     * 		$resultSet->count()
     * @return Number The # of records in this ResultSet.
     */
    public function count() {
        $s = $this->buildSelect(true, null, false, false);
        return $s->numRows();
    }

    /**
     * Deletes all results
     * @return Octopus_Model_ResultSet
     */
    public function delete() {

    	// TODO: Do a batch DELETE?

        foreach ($this as $item) {
            $item->delete();
        }

        return $this;
    }

    /**
     * Sends this ResultSet to dump_r without breaking the chain.
     */
    public function dump() {
        dump_r($this);
        return $this;
    }

    /**
     * Sends the SQL for the current query to dump_r().
     * @return $this to continue the chain.
     */
    public function dumpSql($normalize = true) {

        $params = array();
        $sql = $this->getSql($params);

        if ($normalize) {
            dump_r(normalize_sql($sql, $params));
        } else {
            dump_r($sql, $params);
        }

        return $this;
    }

    /**
     * @return Mixed the first result matched, or false if none were matched.
     */
    public function first() {

        $q = $this->query();
        $row = $q->fetchRow();
        if (!$row) return false;

        return $this->createModelInstance($row);
    }

    /**
     * For a hasOne relation on the model, returns a resultset that contains
     * the contents of that relation for all matched elements.
     * @return Octopus_Model_ResultSet
     */
    public function followRelation($relation) {

        $field = $this->getModelField($relation);
        $table = $this->getModelTableName();
        $relatedModel = $field->getOption('model', $relation);
        $fieldAsID = to_id($field->getFieldName());

        $relatedSet = new Octopus_Model_ResultSet($relatedModel);
        $relatedTable = $relatedSet->getModelTableName();
        $relatedPrimaryKey = $relatedSet->getModelPrimaryKey();

        $dummySelect = new Octopus_DB_Select();
        $params = array();
        $whereClause = $this->getFullWhereClause($dummySelect, $params);
        if (strlen($whereClause)) $whereClause = "WHERE $whereClause";

        return $relatedSet->whereSql(
            "`$relatedTable`.`$relatedPrimaryKey` IN (SELECT `$table`.`$fieldAsID` FROM `$table` $whereClause)",
            $params
        );

    }


    /**
     * @return String The model class this result set holds.
     */
    public function getModel() {
        return $this->_modelClass;
    }

    /**
     * @internal
     * @return Octopus_Model An instance of the model this result set contains.
     * @todo Make field-related stuff on Octopus_Model static so we don't have
     * to keep a dummy instance around.
     */
    public function getModelInstance() {

        if (!$this->_modelInstance) {
            $class = $this->getModel();
            if (!class_exists($class)) {
                throw new Octopus_Model_Exception("Model class not found: $class");
            }
            $this->_modelInstance = new $class();
        }

        return $this->_modelInstance;
    }

    /**
     * @return Octopus_Model_Field
     */
    public function getModelField($name) {
        $mc = $this->_modelClass;
        $obj = new $mc();
        $field = $obj->getField($name);
        return $field;
    }

    /**
     * @param $params Array array into which to put the parameters.
     * @return string The SQL for the current query.
     */
    public function getSql(&$params = null) {

        $s = $this->buildSelect();
        $sql = $s->getSql();

        if ($params !== null) {
            foreach($s->params as $p) {
                $params[] = $p;
            }
        }

        return $sql;
    }

    /**
     * @uses normalize_sql
     */
    public function getNormalizedSql() {
        $params = array();
        $sql = $this->getSql($params);
        return normalize_sql($sql, $params);
    }

    /**
     * @param String $field
     * @return Boolean Whether this result set can be sorted by the given
     * field.
     */
    public function isSortable($field) {
    	return !!$this->getModelField($field);
    }

    /**
     * @param String $field
     * @param Boolean $asc
     * @param Number $index
     * @return Boolean Whether this result set is sorted by $field. If
     * true, $asc is set to whether the sorting is in ascending order or
     * not and $index is set to the (zero-based) index in the sort order
     * $field is.
     */
    public function isSortedBy($field, &$asc = null, &$index = 0) {

    	if (!isset($this->_orderBy[$field])) {
    		return false;
    	}

    	$index = 0;
    	foreach($this->_orderBy as $k => $v) {
    		if ($k == $field) {

    			if (is_string($v) && strcasecmp($v, 'desc') === 0) {
    				$v = false;
    			}

    			$asc = !!$v;

    			return true;
    		}
    		$index++;
    	}

    	return false;
    }

    /**
     * @param $offset Number Record at which to start returning results.
     * @param $maxRecords Mixed Number of records to return. If null, all records are returned.
     * @return Octopus_Model_ResultSet A result set that starts returning records
     * at $offset, and returns at most $maxRecords.
     */
    public function limit($offset, $maxRecords = null) {

        if ($offset === '' || $offset === false) {
            $offset = null;
        } else {
            $offset = max(0, intval($offset));
        }

        if ($maxRecords === '' || $maxRecords === false) {
            $maxRecords = null;
        }

        $maxRecords = ($maxRecords === null ? null : max(0, intval($maxRecords)));

        if ($offset === $this->_offset && $maxRecords === $this->_maxRecords) {
            return $this;
        }

        $result = $this->createChild(null, null, null);
        $result->_offset = $offset;
        $result->_maxRecords = $maxRecords;

        return $result;

    }

    /**
     * returns an optionally keyed array from the result set
     * @return array
     */
    public function map($key, $value = null) {

        // Client-side sorting means we can't use DB_Select's map() stuff
        if ($this->_comparatorFunction) {

            $result = array();
            foreach($this as $item) {

                if ($value) {
                    $result[$item->$key] = $item->$value;
                } else {
                    $result[] = $item->$key;
                }

            }

            return $result;

        }

        if (is_array($key)) {
            $fields = $key;
        } else {
            $fields = array($key);
            if ($value) {
                $fields[] = $value;
            }
        }

        $select = $this->buildSelect(false, $fields);

        if (is_array($key)) {
            return $select->fetchAll();
        } else if ($value) {
            return $select->getMap();
        } else {
            return $select->getOneArray();
        }

    }

    /**
     * Restricts via free-text search.
     * @param String $query
     * @param Octopus_Model_FullTextMatcher $matcher
     * @return Octopus_Model_ResultSet
     * @see Octopus_Model_FullTextMatcher
     * @see Octopus_Model_FullTextMatcher_PHP
     * @uses Octopus_Model::__getSearchFields
     * @uses Octopus_Model::__getFullTextMatcher
     */
    public function matching($query, $matcher = null) {

        $modelClass = $this->getModel();

        if (!$matcher) {
            $matcher = call_user_func(array($modelClass, '__getFullTextMatcher'));
        }

        return $matcher->filter($this, $query);

    }

    /**
     * Sorts the contents of this result set. Calling this will overwrite
     * whatever the current ordering is. To append to the current ordering,
     * use @see thenOrderBy.
     *
     * Examples:
     *
     * <code>
     *  $resultSet->orderBy('name');
     *  $resultSet->orderBy('name ASC');
     *  $resultSet->orderBy(array('name' => true));  // true = ascending
     *  $resultSet->orderBy(array('name' => false)); // false = descending
     *  $resultSet->orderBy('category.name')
     * </code>
     *
     * @return Octopus_Model_ResultSet A new result set sorted by the given
     * arguments.
     */
    public function orderBy(/* Variable */) {

        $child = $this->createChild(null, array(), null);
        $child->_comparatorFunction = null;

        $args = func_get_args();

        return call_user_func_array(array($child, 'thenOrderBy'), $args);
    }

    /**
     * @internal
     * Executes the SQL query backing this result set and returns it.
     * @return Octopus_DB_Result
     */
    public function query() {
        $s = $this->buildSelect();
        return $s->query();
    }


    /**
     * Removes the contents of another result set from this result set and
     * returns the result. Keeps the sorting of $this.
     */
    public function remove(/* Variable */) {
        return $this->derive(func_get_args(), array('AND', 'NOT'));
    }

    /**
     * Sorts the contents of this ResultSet using a callback comparator
     * function. This will override any calls to
     * Octopus_Model_ResultSet::orderBy().
     * @param  Function $function A comparator function that accepts two
     * arguments and returns < 0 if the first should be sorted first, > 0 if
     * the second should, or 0 if they are equivalent.
     * @return Octopus_Model_ResultSet
     */
    public function sortUsing($function) {

        $child = $this->createChild(null, null, null);
        $child->_orderBy = null;
        $child->_comparatorFunction = $function;

        return $child;

    }

    /**
     * Adds a subordering to this resultset.
     * @throws Octopus_Exception If Octopus_Model_ResultSet::sortUsing() was
     * called, thenOrderBy is not supported.
     */
    public function thenOrderBy(/* Variable */) {

        if ($this->_comparatorFunction) {
            throw new Octopus_Exception("thenOrderBy() is not supported after Octopus_Model_ResultSet::sortUsing() has been called.");
        }

        $args = func_get_args();
        $newOrderBy = array();

        foreach($args as $arg) {
            $this->processOrderByArg($arg, $newOrderBy);
        }

        if (empty($newOrderBy)) {
            // Don't create new objects when we don't have to
            return $this;
        }

        $combined = $this->_orderBy;
        foreach($newOrderBy as $key => $value) {
            $combined[$key] = $value;
        }

        return $this->createChild(null, $combined, null);
    }

    /**
     * Undoes a call to ::filter or ::where.
     * @return Octopus_Model_ResultSet
     */
    public function unfilter() {

    	return new Octopus_Model_ResultSet(
    		$this->_modelClass,
    		null,
    		$this->_orderBy
	    );

    }



    /**
     * @return Octopus_Model_ResultSet A copy of this resultset with any limiting
     * restrictions removed.
     */
    public function unlimit() {

        if ($this->_offset === null && $this->_maxRecords === null) {
            return $this;
        }

        $result = $this->createChild(null, null, null);
        $result->_offset = null;
        $result->_maxRecords = null;

        return $result;
    }

    /**
     * @return Octopus_Model_ResultSet A ResultSet derived from this one with
     * additional filters applied.
     */
    public function where(/* Variable */) {
        $args = func_get_args();
        return $this->createChild(func_get_args(), null, 'AND');
    }

    /**
     * Enables filtering the result set using literal SQL. If you are using
     * this directly, maybe you shouldn't?
     * @param $sql String The SQL to inject into the WHERE clause
     * @param $params Array Any paramters referenced by $sql
     */
    public function whereSql($sql, $params = array()) {

        if (!is_array($params)) {
            $args = func_get_args();
            array_shift($args);
            $params = $args;
        }

        $result = $this->where(array($sql => $params));

        return $result;
    }

 	////////////////////////////////////////
 	//
 	// Protected Methods
 	//
 	////////////////////////////////////////

    /**
     * Takes a big fat criteria array and generates a WHERE clause.
     * @param $criteria Array Set of criteria, pre-filtered by normalizeCriteria
     * @param $s Octopus_DB_Select being constructed.
     * @param $params Array Set of parameters referenced by the generated SQL.
     * @return string SQL for a WHERE clause (minus the WHERE keyword)
     */
    protected function buildWhereClause($criteria, $s, &$params) {

        /* Examples of what $criteria might be:
         *
         *  array('field1' => 'value1', 'field2' => 'value2')
         *  array(array('field1' => 'value1'), 'AND', array('field2' => 'value2'))
         *  array('NOT' => array('field1' => 'value1'))
         */

        $sql = '';
        $conjunction = '';

        foreach($criteria as $key => $value) {

            $expression = '';

            if ($key === 'AND' || $key === 'OR') {

                // e.g., array('AND' => array('field1' => 'value1'))
                $conjunction = $key;
                $expression = self::buildWhereClause($value, $s, $params);

            } else if ($key === 'NOT') {

                // e.g. array('NOT' => array('field1' => 'value1'))
                $expression = trim(self::buildWhereClause($value, $s, $params));

                if (strlen($expression)) {
                    $expression = "$key ($expression)";
                }

            } else if (is_array($value)) {

                $expression = $this->buildWhereClause($value, $s, $params);

            } else if ($value instanceof Octopus_Model_Restriction) {

                $expression = $value->getSql($s, $params);

            } else if ($value === 'AND' || $value === 'OR') {

                    // e.g. array('field1' => 'value1', 'AND', 'field2' => 'value2')
                    $conjunction = $value;

            } else {
                throw new Octopus_Model_Exception("Unhandled key/value in criteria: $key, $value");
            }

            $expression = trim($expression);
            if (strlen($expression)) {
                $sql = self::joinSql($conjunction ? $conjunction : 'AND', $sql, $expression);
                $conjunction = '';
            }
        }

        return $sql;
    }

    /**
     * @return An Octopus_Model_ResultSet based on this one.
     */
    protected function createChild($criteria, $orderBy, $conjunction) {

        if ($orderBy === null) {
            $orderBy = $this->_orderBy;
        }

        $child = new Octopus_Model_ResultSet($this, $criteria, $orderBy, $conjunction);
        $child->escaped = $this->escaped;

        $child->_offset = $this->_offset;
        $child->_maxRecords = $this->_maxRecords;
        $child->_comparatorFunction = $this->_comparatorFunction;

        return $child;
    }

    /**
     * Factory method used to generate an instance of Octopus_Model from the given
     * row of data.
     * @return Object A new model instance from the given row.
     */
    protected function createModelInstance(&$row) {

        // NOTE: Because $row might contain extra fields (like from a join,
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
     * @return A new Octopus_Model_ResultSet based on $this, adding the
     * given criteria w/ the given conjunction.
     */
    protected function derive($criteria, $conjunction) {

        $result = $this;
        $lastFieldName = null;

        foreach($criteria as $arg) {

            if ($arg instanceof Octopus_Model) {
                $arg = array('id' => $arg->id);
            }

            if ($arg instanceof Octopus_Model_ResultSet) {
                $result = $this->createChild($arg->_criteria, null, $conjunction);
                $lastFieldName = null;
            } else if (is_array($arg)) {
                $result = $this->createChild($arg, null, $conjunction);
                $lastFieldName = null;
            } else if (is_string($arg)) {

                if ($lastFieldName === null) {
                    $lastFieldName = $arg;
                } else {
                    $result = $this->createChild(array($lastFieldName => $arg), null, $conjunction);
                    $lastFieldName = null;
                }

            } else {
                throw new Octopus_Exception('Unsupported arg to Octopus_Model_ResultSet::derive(): ' . $arg);
            }

        }

        return $result;

    }

    /**
     * This method supports ResultSet's (currently non-optimal) implementation
     * of ArrayAccess as well as client-side sorting (::sortUsing()).
     * @return Array Every single item returned as an array.
     */
    protected function &getItemsAsArray() {

        if ($this->_itemArray !== null) {
            return $this->_itemArray;
        }

        $this->_itemArray = array();
        $q = $this->query();

        while($row = $q->fetchRow()) {

            $item = $this->createModelInstance($row);
            $this->_itemArray[] = $item;

        }

        if ($this->_comparatorFunction) {

            // Error suppression is because of
            // https://bugs.php.net/bug.php?id=50688
            @usort($this->_itemArray, $this->_comparatorFunction);

        }

        if ($this->_offset && $this->_maxRecords) {

            $this->_itemArray = array_slice($this->_itemArray, $this->_offset, $this->_maxRecords);

        } else if ($this->_maxRecords) {

            $this->_itemArray = array_slice($this->_itemArray, 0, $this->_maxRecords);

        } else if ($this->_offset) {

            $this->_itemArray = array_slice($this->_itemArray, $this->_offset);

        }

        return $this->_itemArray;

    }

    protected function getModelPrimaryKey() {
        $instance = $this->getModelInstance();
        return $instance->getPrimaryKey();
    }

    protected function getModelTableName() {
        $instance = $this->getModelInstance();
        return $instance->getTableName();
    }

    ////////////////////////////////////////
    //
    // Private Methods
    //
    ////////////////////////////////////////

    private function applyOrderByClause(&$s, &$orderBy) {

        if (empty($orderBy)) {
            return;
        }

        foreach($orderBy as $fieldName => $dir) {

            // Special-case 'RAND()',
            if (strcasecmp('RAND()', $fieldName) === 0) {
                $s->orderBy('RAND()');
                continue;
            }

            $info = Octopus_Model_Restriction_Field::parseFieldExpression($fieldName, $this->getModelInstance());
            extract($info);

            if ($field === $this->getModelPrimaryKey()) {
                Octopus_Model_Field::defaultOrderBy($field, $dir, $s, $s->params, $this->getModelInstance());
                continue;
            }

            $f = $this->getModelField($field);

            if (!$f) {
                throw new Octopus_Model_Exception("Field not found on {$this->getModel()}: $field");;
            }

            $f->orderBy($subexpression, $dir, $s, $s->params, $this->getModelInstance());
        }

    }

    /**
     * @return Object A new Octopus_DB_Select instance.
     */
    private function buildSelect($recreate = false, $fields = null, $order = true, $limit = true) {

        $recreate = $recreate || ($fields !== null || !$order || !$limit);

        if (!$recreate && $this->_select) {
            return $this->_select;
        }

        if (is_array($fields)) {

            foreach($fields as $index => $name) {

                // Allow using 'id' as an alias for primary key
                if ($name === 'id') {
                    $fields[$index] = $this->getModelPrimaryKey();
                }

            }

        }

        $s = new Octopus_DB_Select();
        $s->table($this->getModelTableName(), $fields);

        $params = array();
        $whereClause = trim($this->getFullWhereClause($s, $params));

        if (strlen($whereClause)) {
            $s->where($whereClause, $params);
        }

        if ($order) {
            $this->applyOrderByClause($s, $this->_orderBy);
        }

        // NOTE: if client-side sorting is being used, we can't limit in SQL
        if ($limit && ($this->_offset !== null || $this->_maxRecords !== null) && !$this->_comparatorFunction) {
            $s->limit(($this->_offset === null ? 0 : $this->_offset), $this->_maxRecords);
        }

        if ($fields === null && $order && $limit) {
            $this->_select = $s;
        }

        return $s;
    }

    /**
     * @return String The full WHERE clause for this result set, including
     * any restrictions placed by the parent.
     */
    private function getFullWhereClause($s, &$params) {
        return self::joinSql(
            $this->_conjunction ? $this->_conjunction : 'AND',
            $this->_parent ? $this->_parent->getFullWhereClause($s, $params) : '',
            $this->buildWhereClause($this->_criteria, $s, $params)
        );
    }

    /**
     * Combines two pieces of a where clause.
     */
    private static function joinSql($conj, $left, $right) {

        $left = trim($left);
        $right = trim($right);

        $leftLen = strlen($left);
        $rightLen = strlen($right);

        if (!($leftLen || $rightLen)) {
            return '';
        } else if ($leftLen && !$rightLen) {
            return $left;
        } else if ($rightLen && !$leftLen) {
            return $right;
        }

        if (!is_array($conj)) {
            return '(' . $left . ') ' . $conj . ' (' . $right . ')';
        }

        $left = "($left) ";

        foreach($conj as $c) {
            $left .= "$c (";
            $right .= ')';
        }

        return $left . $right;
    }

    /**
     * Handles a single argument passed to orderBy(). This could be a string
     * (e.g. 'whatever DESC') or an array (e.g. array('whatever' => 'DESC') ).
     * @return boolean TRUE if something is made of the argument,
     * FALSE otherwise.
     */
    private function processOrderByArg($arg, &$orderBy) {

        if (is_string($arg)) {

            $arg = trim(preg_replace('/\s+/', ' ', $arg));
            if (!$arg) return false;

            $spacePos = strrpos($arg, ' ');

            if ($spacePos === false) {
                // No order provided, so default to ascending
                $orderBy[$arg] = 'ASC';
            } else {
                $dir = strtoupper(substr($arg, $spacePos + 1));
                $arg = substr($arg, 0, $spacePos);
                $orderBy[$arg] = $dir;
            }

            return true;
        }

        if (is_array($arg)) {

            $processed = 0;
            foreach($arg as $field => $dir) {

                if (is_numeric($field)) {

                    // this is an entry at a numeric index, e.g.
                    //       vvvvvv
                    // array('name', 'created' => 'desc')

                    if ($this->processOrderByArg(array($dir => 'ASC'), $orderBy)) {
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
     * Given a set of criteria in any of the following formats:
     *
     *  array('field', 'value')
     *  array('field1' => 'value1', 'field2' => 'value2')
     *  array('field1' => 'value1', 'OR', array('field2' => 'value2'))
     *  array('NOT' => array('field1' => 'value1'))
     *
     *  and returns an array in a standard format:
     *
     *  array(
     *      array('field1' => 'value1'),
     *      'OR',
     *      array('field2' => 'value2')
     *  )
     *
     * @param $criteria Array Criteria to normalize.
     */
    private function &normalizeCriteria($criteria) {

        $result = array();
        $lastFieldName = null;

        foreach($criteria as $key => $value) {

            if (is_numeric($key)) {

                // $value is either a field name, value, conjunction, literal sql, or another array of criteria

                if ($lastFieldName !== null) {
                    $result[] = new Octopus_Model_Restriction_Field($this->getModelInstance(), $lastFieldName, $value);
                    $lastFieldName = null;
                } else if (is_array($value)) {
                    $result[] = $this->normalizeCriteria($value);
                    $lastFieldName = null;
                } else if ($value instanceof Octopus_Model_Restriction) {
                    $result[] = $value;
                } else {

                    $uvalue = strtoupper($value);

                    if ($uvalue === 'OR' || $uvalue === 'AND') {
                        if (count($result)) {
                            $result[] = $uvalue;
                        }
                        $lastFieldName = null;
                    } else if ($lastFieldName === null) {

                        if (Octopus_Model_Restriction_Field::looksLikeFieldExpression($value)) {
                            $lastFieldName = $value;
                        } else {
                            // Probably literal SQL
                            $result[] = new Octopus_Model_Restriction_Sql($value);
                        }

                    }
                }

                continue;
            }

            // $key is either a field name or a conjunction or NOT
            $ukey = strtoupper($key);

            if ($ukey === 'NOT') {
                $result[] = array('NOT' => $this->normalizeCriteria($value));
            } else if ($ukey === 'AND' || $ukey === 'OR') {
                $result[] = $ukey;
                $result[] = $this->normalizeCriteria($value);
            } else {
                // Two scenarios:
                //  1. $key is a field name and $value is a corresponding filter value
                //  2. $key is literal SQL and $value is an array of parameters to go with it
                if (Octopus_Model_Restriction_Field::looksLikeFieldExpression($key)) {
                    $result[] = new Octopus_Model_Restriction_Field($this->getModelInstance(), $key, $value);
                } else {
                    $result[] = new Octopus_Model_Restriction_Sql($key, $value);
                }
            }
        }

        return $result;
    }

    /**
     * Handles the where____ magic method for boolean fields.
     * @param $matches Array set of matched groups from the magic method pattern.
     */
    private function whereBoolean($matches) {

        $field = underscore($matches['field']);
        $not = isset($matches['not']) ? (strcasecmp('not', $matches['not']) == 0) : false;

        return $this->where(array($field => $not ? 0 : 1));
    }

    ////////////////////////////////////////
    //
    // Internal Public Methods
    //
    ////////////////////////////////////////

    /**
     * @internal
     * Supports "magic" methods, such as whereField (same as where(field, 1)).
     * @throws Octopus_Exception_MethodMissing Thrown if $name does not
     * resolve to an actual method.
     */
    public function __call($name, $args) {

        foreach(self::$_magicMethods as $pattern => $handler) {
            if (preg_match($pattern, $name, $m)) {
                return $this->$handler($m);
            }
        }

        throw new Octopus_Exception_MethodMissing($this, $name, $args);
    }

    /**
     * @internal
     * @see Dumpable::__dumpHtml()
     * @return String HTML debugging info for this result set.
     */
    public function __dumpHtml() {

        $class = $this->getModel();
        $classPlural = pluralize(humanize($this->getModel()));
        $count = count($this);

        $html = <<<END
<table class="octopusDebugResultSetData octopusDebugBordered" border="0" cellpadding="0" cellspacing="0">
<thead>
<tr>
    <th colspan="1000">
        <span class="octopusDebugResultSetClass">Result Set for $class - $count rows</span>
    </th>
</tr>
END;


        $dummy = new $class();
        $fields = $dummy->getFields();

        $html .= '<tr><th>' . $dummy->getPrimaryKey() . '</th>';

        foreach($fields as $f) {
            $html .= '<th>' . h($f->getFieldName()) . '</th>';
        }

        $html .= '</tr></thead><tbody>';

        $index = 0;

        foreach($this as $model) {

            if ($index >= 20) {
                $html .= <<<END
<tr><td class="octopusDebugResultSetStop" colspan="1000">Stopping at $index</td></tr>
END;
                break;
            }

            $d = $model->toArray();

            $class = $index % 2 ? 'octopusDebugOdd' : 'octopusDebugEven';
            $html .= <<<END
<tr class="$class">
<td>{$model->id}</td>
END;

            foreach($fields as $f) {
                $value = $d[$f->getFieldName()];
                if (is_object($value)) {
                    try {
                        $value = trim($value->__toString());
                    } catch (Exception $ex) {
                        $value = "<Exception during __toString: $ex>";
                    }
                }
                $html .=
                    '<td>' .
                    h($value).
                    '</td>';

            }

            $html .= '</tr>';

            $index++;
        }

        $html .= '</tbody></table>';


        $html .=
            '<h3>SQL</h3>' .
            '<textarea class="octopusDebugResultSetSql">' .
            h($this->__dumpText()) .
            '</textarea>';


        return $html;
    }

    /**
     * @internal
     * @see Dumpable::__dumpText
     * @return String SQL for this result set.
     */
    public function __dumpText() {

        $params = array();
        $sql = $this->getSql($params);
        $sql = normalize_sql($sql, $params);

        $class = get_class($this);
        $model = $this->getModel();
        $count = null;

        try {
        	$count = count($this);
        } catch(Exception $ex) {
        	$count = "<ERROR>";
        }

		return "$class<$model> (count: $count) $sql";
    }

    /**
     * @internal
     * Supports Iterator implementation.
     * @return Octopus_Model
     */
    public function current() {
        return $this->_iteratorCurrentItem;
    }

    /**
     * @internal
     * Supports Octopus_DataSource implementation. Use ::where() instead.
     * @return Octopus_Model_ResultSet
     */
    public function filter($field, $value) {
        return $this->where($field, $value);
    }

    /**
     * @internal
     * Supports Iterator implementation.
     * @return Number|Boolean
     */
    public function key() {

        $current = $this->current();
        return $current ? $current->id : false;

    }

    /**
     * @internal
     * Supports ArrayAccess implementation.
     */
    public function offsetExists($offset) {
        $all = $this->getItemsAsArray();
        return isset($all[$offset]);
    }

    /**
     * @internal
     * Supports ArrayAccess implementation.
     */
    public function offsetGet($offset) {
        $all = $this->getItemsAsArray();
        if (isset($all[$offset])) {
            return $this->createModelInstance($all[$offset]);
        }

        return null;
    }

    /**
     * @internal
     * Supports ArrayAccess implementation.
     * @throws Octopus_Model_Exception
     */
    public function offsetSet($offset, $value) {
        // no, you can't do this
        throw new Octopus_Model_Exception('You cannot set this, that does not make sense.');
    }

    /**
     * @internal
     * Supports ArrayAccess implementation.
     * @throws Octopus_Model_Exception
     */
    public function offsetUnset($offset) {
        // no, you can't do this
        throw new Octopus_Model_Exception('You cannot unset this, that does not make sense.');
    }

    /**
     * @internal
     * Supports Iterator implementation.
     */
    public function next() {

        if ($this->_iteratorQuery !== null) {

            $row = $this->_iteratorQuery->fetchRow();
            $this->_iteratorCurrentItem = ($row ? $this->createModelInstance($row) : null);

        } else {

            $this->_iteratorIndex++;

            if ($this->_iteratorIndex < 0 || $this->_iteratorIndex >= count($this->_iteratorItems)) {
                $this->_iteratorCurrentItem = null;
            } else {
                $this->_iteratorCurrentItem = $this->_iteratorItems[$this->_iteratorIndex];
            }

        }

    }

    /**
     * @internal
     * Supports Iterator implementation.
     */
    public function rewind() {

        $this->_iteratorQuery = null;
        $this->_iteratorItems = null;
        $this->_iteratorIndex = 0;
        $this->_iteratorCurrentItem = null;

        if ($this->_comparatorFunction) {

            // We're using a client-side comparator function, so we have to
            // load everything into an array and then sort things.
            $this->_iteratorItems = $this->getItemsAsArray();
            $this->_iteratorCurrentItem = $this->_iteratorItems ? $this->_iteratorItems[0] : null;

        } else {

            // No client-side sorting is happening, so we can safely
            // traverse row-at-a-time
            $this->_iteratorQuery = $this->query();

            $row = $this->_iteratorQuery->fetchRow();
            if ($row) {
                $this->_iteratorCurrentItem = $this->createModelInstance($row);
            }

        }

    }

    /**
     * @internal
     * Supports Octopus_DataSource implementation.
     * @see Octopus_DataSource
     * @return Octopus_ResultSet
     */
    public function sort($col, $asc = true, $replace = true) {

        if ($replace) {
            return $this->orderBy(array($col => $asc));
        } else {
            $newOrderBy = array($col => $asc);
            if ($this->_orderBy) {
                foreach($this->_orderBy as $c => $a) {
                    if (!isset($newOrderBy[$c])) {
                        $newOrderBy[$c] = $a;
                    }
                }
            }
            return $this->orderBy($newOrderBy);
        }
    }

    /**
     * @internal
     * Supports Octopus_DataSource implementation.
     * @return Octopus_Result_Set
     */
    public function unsort() {
    	return $this->orderBy();
    }

    /**
     * @internal
     * Supports Iterator implementation.
     * @return Boolean
     */
    public function valid() {

        if ($this->_iteratorQuery) {

            return $this->_iteratorCurrentItem !== null;

        } else {

            return $this->_iteratorIndex >= 0 && $this->_iteratorIndex < count($this->_iteratorItems);

        }

    }


}
