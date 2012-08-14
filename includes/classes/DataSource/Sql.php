<?php

/**
 * DataSource implementation that works with raw sql.
 * @todo TEST!
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_DataSource_Sql implements Octopus_DataSource {

	private $sql;
	private $db;
	private $sorting = array();
	private $filters = array();

	public function __construct($sql) {
		$this->sql = $sql;
		$this->db = Octopus_DB::singleton();
	}

	public function count() {
        $countSql = preg_replace('/select[ ]+\*[ ]+from/i', 'SELECT COUNT(*) FROM', $this->sql);
        return $this->db->getOne($countSql);
	}

	public function filter($field, $value) {
		$result = new Octopus_DataSource_Sql($this->sql);
		$result->sorting = $this->sorting;
		$result->filters = $this->filters;
		$result->filters[] = array($field, $value);
	}

	public function getItems($start = 0, $count = 0) {

		$sql = $this->sql;
		$params = array();

		// TODO: handle pre-existing WHERE clauses

		$needWhere = true;
		foreach($this->filters as $f) {
			list($field,$value) = $f;
			$sql .= ($needWhere ? ' WHERE ' : '');
			$sql .= "(`$field` = ?)";
			$params[] = $value;
			$needWhere = false;
		}

		// sort
        $needOrderBy = true;
        foreach ($this->sorting as $field => $asc) {
        	$dir = ($asc ? 'ASC' : 'DESC');
            $sql .= ($needOrderBy ? ' ORDER BY ' : ', ');
            $sql .= "`$field` $dir";
            $needOrderBy = false;
        }

        // then limit
        if ($start || $count) {
	        $sql .= sprintf(' LIMIT %d', $start);
	        if ($count) $sql .= sprintf(', %d', $count);
	    }

        $query = $db->query($sql, true, $params);
        $items = array();
        while ($items[] = $query->fetchRow()) {}

        return $items;
	}

	public function sort($field, $asc) {

		if (strcasecmp($asc, 'asc') === 0) {
			$asc = true;
		} else if (strcasecmp($asc, 'desc') === 0) {
			$asc = false;
		}

		$newSorting = array($field => $asc);
		foreach($this->sorting as $field => $asc) {
			if (!isset($newSorting[$field])) {
				$newSorting[$field] = $asc;
			}
		}

		$result = new Octopus_DataSource_Sql($this->sql);
		$result->sorting = $newSorting;
		$result->filters = $this->filters;

		return $result;
	}

}

