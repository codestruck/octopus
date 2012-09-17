<?php

/**
 * A restriction that joins one or more other restrictions using a conjunction
 * (AND or OR).
 */
class Octopus_Model_Restriction_Composite implements Octopus_Model_Restriction {

	private $conjunction;
	private $restrictions = array();

	public function __construct($conjunction = 'AND') {
		$this->conjunction = $conjunction;
	}

	/**
	 * @param Octopus_Model_Restriction $restriction
	 */
	public function add(Octopus_Model_Restriction $restriction) {
		$this->restrictions[] = $restriction;
	}

	public function getSql(Octopus_DB_Select $s, Array &$params) {

		if (empty($this->restrictions)) {
			return '';
		}

		$result = array();
		foreach($this->restrictions as $r) {
			$sql = $r->getSql($s, $params);
			if ($sql) $result[] = $sql;
		}

		if (empty($result)) {
			return '';
		}

		return '(' . implode(") {$this->conjunction} (", $result) . ')';

	}

}