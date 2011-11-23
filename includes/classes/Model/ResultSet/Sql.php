<?php

/**
 * ResultSet that takes a raw SQL query.
 */
class Octopus_Model_ResultSet_Sql extends Octopus_Model_ResultSet {

	private $sql;
	private $params;
	private $count = 0;

	public function __construct($modelClass, $sql, $params = array()) {

		parent::__construct($modelClass);
		$this->sql = $sql;
		$this->params = $params;

	}

    public function getSql(&$params = null) {
    	$params = $this->params;
    	return $this->sql;
    }

    // These are all no-ops
    // TODO: maybe add callbacks for sorting / paging / filtering?
    public function add() { return $this; }
    public function remove() { return $this; }
    public function contains() { return $this; }
    public function followRelation($relation) { throw new Octopus_Exception("Not implemented: " . __METHOD__); }
    public function whereSql($sql, $params = array()) { return $this; }
    public function limit($offset, $maxRecords = null ) { return $this; }
    public function unlimit() { return $this; }
    public function matching($text) { return $this; }
    public function where() { return $this; }
    public function orderBy() { return $this; }
    public function thenOrderBy() { return $this; }
    public function count($useLimit = false) { return $this->count; }

    public function setCount($count) {
    	$this->count = $count;
    }

    protected function &query($new = false) {

        if ($this->query && !$new) {
            return $this->query;
        }

        $db = Octopus_DB::singleton();
        $this->query = $db->query($this->sql, true, $this->params);

        return $this->query;
    }

}

?>