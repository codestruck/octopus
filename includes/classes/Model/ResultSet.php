<?php

/**
 * Class that handles searching for SG_Model instances.
 */
class SG_Model_ResultSet implements Iterator {

    private $_parent;
    private $_modelClass;
    private $_criteria;
    private $_orderBy;

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
        return $this->_restrict('AND', $args);
    }

    /**
     * @return Number The # of records in this ResultSet.
     */
    public function count() {
    }

    /**
     * Sends the SQL for the current query to dump_r().
     * @return $this to continue the chain.
     */
    public function &dumpSql() {
        dump_r($this->getSql());
        return $this;
    }

    /**
     * @return Mixed the first result matched, or false if none were matched.
     */
    public function &first() {

    }

    /**
     * @return string The SQL for the current query.
     */
    public function getSql() {
        return 'oh noes!';
    }

    /**
     * @return Object A new ResultSet with extra constraints added via OR.
     */
    public function &or_(/* Variable */) {
        $args = func_get_args();
        return $this->_restrict('OR', $args);
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

        return new SG_Model_ResultSet($this->_modelClass, $this->_criteria, $newOrderBy);
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
     * Handles a single 'order by' argument. This could be a string (e.g.
     * 'whatever DESC') or an array (e.g. array('whatever' => 'DESC') ).
     * @return boolean TRUE if something is made of the argument, FALSE otherwise.
     */
    private function &_processOrderByArg($arg, &$orderBy) {

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

        $criteria = array();
        while(count($args)) {

            $c = array_shift($args);

        }

        if (empty($criteria)) {
            return $this;
        }

        return new SG_

    }

    /**
     * Handles the where____ magic method for boolean fields.
     * @param $matches Array set of matched groups from the magic method pattern.
     */
    private function &_whereBoolean($matches) {

        $field = $matches['field'];
        $not = isset($matches['not']) ? (strcasecmp('not', $matches['not']) == 0) : false;

        return $this->and($field, $not ? 0 : 1);
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
    }

    public function key() {
    }

    public function next() {
    }

    public function rewind() {
    }

    public function valid() {
    }


    // }}}

    /**
     * Takes a variable number of arguments and turns them into a standardized
     * criteria array.
     */
    public static function &makeCriteriaArray(/* variable */) {

        $args = func_get_args();
        $lastField = null;
        $criteria = array();

        foreach($args as $arg) {

            if (is_array($arg)) {
                // array('field' => 'value')
                $criteria[] = $arg;
                $lastField = null;
            } else if (is_string($arg) && !$lastField) {
                $lastField = $arg;
            } else if ($lastField) {
                // handle 'field', 'value'
                $criteria[$lastField] = $arg;
            }

        }


        return $criteria;
    }

}


?>
