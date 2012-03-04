<?php

/**
 * Class that handles searching for Octopus_Model instances.
 */
class Octopus_Model_ResultSet implements ArrayAccess, Countable, Iterator, Dumpable {

    public $escaped = false;

    private $_parent;
    private $_conjunction = '';

    private $_modelClass;
    private $_modelInstance = null;

    private $_criteria;
    private $_orderBy;
    private $_select;
    protected $query;

    private $_currentQuery = null;
    private $_current = null;
    private $_arrayAccessResults = null;

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
     * @param $parentOrModelClass mixed Either another Octopus_Model_ResultSet
     * to build upon or the name of a model class for which to create a new
     * result set.
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

    // Public Methods {{{

    /**
     * Appends the contents of another result set to this result set and
     * returns the result. Maintains sorting of $this.
     */
    public function add(/* Variable */) {
        return $this->derive(func_get_args(), 'OR');
    }

    /**
     * Removes the contents of another result set from this result set and
     * returns the result. Keeps the sorting of $this.
     */
    public function remove(/* Variable */) {
        return $this->derive(func_get_args(), array('AND', 'NOT'));
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
     * Deletes all results
     */
    public function delete() {

        foreach ($this as $item) {
            $item->delete();
        }

        $this->query = null;
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

    public function __dumpText() {
        $params = array();
        $sql = $this->getSql($params);
        return normalize_sql($sql, $params);
    }

    /**
     * @return Mixed the first result matched, or false if none were matched.
     */
    public function first() {

        $q = $this->query(true);
        $row = $q->fetchRow();
        if (!$row) return false;

        return $this->createModelInstance($row);
    }

    /**
     * For a hasOne relation on the model, returns a resultset that contains
     * the contents of that relation for all matched elements.
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
     * @return Octopus_Model_ResultSet A copy of this resultset with any limiting
     * restrictions removed.
     */
    public function unlimit() {

        if ($this->_offset === null && $this->_maxRecords === null) {
            return $this;
        }

        return $this->createChild(null, null, null);
    }

    /**
     * @return String The model class this result set holds.
     */
    public function getModel() {
        return $this->_modelClass;
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

    public function getNormalizedSql() {
        $params = array();
        $sql = $this->getSql($params);
        return normalize_sql($sql, $params);
    }

    /**
     * Restricts via free-text search.
     */
    public function matching($text) {
        return $this->where($this->createFreeTextCriteria($text));
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
        $args = func_get_args();

        return call_user_func_array(array($child, 'thenOrderBy'), $args);
    }

    /**
     * Adds a subordering to this resultset.
     */
    public function thenOrderBy(/* Variable */) {

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
     * @return Octopus_Model_ResultSet A ResultSet derived from this one with
     * additional filters applied.
     */
    public function where(/* Variable */) {
        $args = func_get_args();
        return $this->createChild(func_get_args(), null, 'AND');
    }

    /**
     * returns an optionally keyed array from the result set
     * @return array
     */
    public function map($key, $value = null) {

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

    // End Public Methods }}}

    // Protected Methods {{{

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

        return $child;
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

                $type = $field->getOption('type', 'text');
                $isText = ($type == 'string' || $type == 'hasOne');

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

            $restrict = $field->restrictFreetext($dummy, $text);

            if ($restrict) {
                if (count($criteria)) $criteria[] = 'OR';
                $criteria[] = $restrict;
            }

        }

        return $criteria;

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
     * @return Octopus_Model An instance of the model this result set contains.
     */
    protected function getModelInstance() {

        // TODO: Lots of these methods should be static on Octopus_Model.

        if (!$this->_modelInstance) {
            $class = $this->getModel();
            if (!class_exists($class)) {
                throw new Octopus_Model_Exception("Model class not found: $class");
            }
            $this->_modelInstance = new $class();
        }

        return $this->_modelInstance;
    }

    protected function getModelPrimaryKey() {
        $instance = $this->getModelInstance();
        return $instance->getPrimaryKey();
    }

    protected function getModelTableName() {
        $instance = $this->getModelInstance();
        return $instance->getTableName();
    }

    // End Protected Methods }}}

    // Private Methods {{{

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
    private function buildSelect($recreate = false, $fields = null) {

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

        $this->applyOrderByClause($s, $this->_orderBy);

        if ($this->_offset !== null || $this->_maxRecords !== null) {
            $s->limit(($this->_offset === null ? 0 : $this->_offset), $this->_maxRecords);
        }

        $this->_select = $s;
        return $this->_select;
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
     * Executes the SQL query backing this ResultSet.
     * @return Octopus_DB_Result
     */
    protected function &query($new = false) {

        if ($this->query && !$new) {
            return $this->query;
        }

        $s = $this->buildSelect();
        $this->query = $s->query();

        return $this->query;
    }

    // End Private Methods }}}

    // Static Methods {{{

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

    // }}}

    // Magic Methods {{{

    /**
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
     * Handles the where____ magic method for boolean fields.
     * @param $matches Array set of matched groups from the magic method pattern.
     */
    private function whereBoolean($matches) {

        $field = underscore($matches['field']);
        $not = isset($matches['not']) ? (strcasecmp('not', $matches['not']) == 0) : false;

        return $this->where(array($field => $not ? 0 : 1));
    }

    // End Magic Methods }}}

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
        $this->_currentQuery = $this->query(true);
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

        $this->_current = $this->createModelInstance($row);
        return true;
    }

    /**
     * @return Number The # of records in this ResultSet.
     * @param $considerLimit Boolean Whether If true, and the resultset has
     * been limited using limit(), then the result of count() will be at
     * most the # of records the resultset is limited to.
     */
    public function count($considerLimit = true) {

        $s = $this->buildSelect();
        $count = $s->numRows();

        if ($considerLimit && $this->_maxRecords !== null) {
            return min($this->_maxRecords, $count);
        }

        return $count;
    }

    // }}}

    // ArrayAccess Implementation {{{

    private function getArrayAccessResult() {
        if (!$this->_arrayAccessResults) {
            $query = $this->query(true);
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
            return $this->createModelInstance($all[$offset]);
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
