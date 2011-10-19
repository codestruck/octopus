<?php

/**
 * ResultSet that takes a raw SQL query.
 */
class Octopus_Model_ResultSet_Sql extends Octopus_Model_ResultSet {

	private $sql;
	private $params;

	public function __construct($modelClass, $sql, $params = array()) {

		parent::__construct($modelClass);
		$this->sql = $sql;
		$this->params = $params;

	}


    public function getSql(&$params = null) {
    	$params = $this->params;
    	return $this->sql;
    }

    protected function &query($new = false) {

        if ($this->query && !$new) {
            return $this->query;
        }

        $db = Octopus_DB::singleton();
        return $this->query = $db->query($this->sql, true, $this->params);
    }

}

?>