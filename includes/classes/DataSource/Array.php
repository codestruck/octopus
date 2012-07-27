<?php

/**
 * Implementation of Octopus_DataSource that wraps an array and provides
 * paged access to it.
 */
class Octopus_DataSource_Array implements Octopus_DataSource {

	private $array;
	private $filters = array();
	private $sorting = array();
	private $start = 0;
	private $limit = null;
	private $workingArray = null;
	private $workingArraySorted = false;
	private $iteratorIndex = -1;

	public function __construct($array, $sorting = array(), $filters = array(), $start = 0, $limit = null) {
		$this->array = $array;
		$this->sorting = $sorting;
		$this->filters = $filters;
		$this->start = $start;
		$this->limit = $limit;
	}

	public function count() {
		return count($this->getWorkingArray());
	}

	public function filter($field, $value) {

		$newFilters = $this->filters;
		$newFilters[$field] = $value;

		return new Octopus_DataSource_Array($this->array, $this->sorting, $newFilters, $this->start, $this->limit);
	}

	public function unfilter() {

		if (empty($this->filters)) {
			return $this;
		}

		return new Octopus_DataSource_Array($this->array, $this->sorting, array(), $this->start, $this->limit);

	}

	/**
	 * @return Array The actual filtered, sorted, and limited array.
	 */
	public function getArray() {

		$result = array();
		foreach($this as $item) {
			$result[] = $item;
		}

		return $result;
	}

	public function limit($start, $count = null) {

		if ($this->start === $start && $this->limit === $count) {
			return $this;
		}

		return new Octopus_DataSource_Array($this->array, $this->sorting, $this->filters, $start, $count);
	}

	public function unlimit() {

		if ($this->start === 0 && $this->limit === null) {
			return $this;
		}

		return new Octopus_DataSource_Array($this->array, $this->sorting, $this->filters, 0, null);

	}

	public function isSortable($field) {

		foreach($this->array as $item) {

			if (isset($item[$field])) {
				return true;
			}

		}

		return false;

	}

	public function isSortedBy($field, &$asc = null, &$index = 0) {

		if (!isset($this->sorting[$field])) {
			return false;
		}

		$index = 0;

		foreach($this->sorting as $f => $a) {

			if ($f == $field) {
				$asc = $a;
				return true;
			}

			$index++;

		}

		return false; // should never reach here
	}

	public function sort($field, $asc = true, $replace = true) {

		$sorting = $replace ? array() : $this->sorting;
		$newSorting = array($field => $asc);

		foreach($sorting as $key => $value) {
			$newSorting[$key] = $value;
		}

		return new Octopus_DataSource_Array($this->array, $newSorting, $this->filters, $this->start, $this->limit);
	}

	public function unsort() {

		if (empty($this->sorting)) {
			return $this;
		}

		return new Octopus_DataSource_Array($this->array, array(), $this->filters, $this->start, $this->limit);

	}

	public function current() {
		$ar = $this->getWorkingArray();
		return $ar[$this->iteratorIndex + $this->start];
	}

	public function key() {
		return $this->iteratorIndex;
	}

	public function next() {
		$this->iteratorIndex++;
	}

	public function rewind() {
		$this->iteratorIndex = 0;
	}

	public function valid() {

		if ($this->iteratorIndex < 0) {
			return false;
		}

		if ($this->iteratorIndex + $this->start >= count($this->getWorkingArray(false))) {
			return false;
		}

		if ($this->limit !== null && $this->iteratorIndex >= $this->limit) {
			return false;
		}

		return true;
	}

	protected function &getWorkingArray($sort = true) {

		if ($this->workingArray === null) {

			$this->workingArray = $this->applyFilters($this->array, $this->filters);

		}

		if ($sort && !$this->workingArraySorted) {
			$this->sortArray($this->workingArray);
		}

		return $this->workingArray;

	}

	private function &applyFilters($array, $filters) {

		if (empty($filters)) {
			return $array;
		}

		$result = array();
		foreach($array as $item) {

			$include = true;

			foreach($filters as $key => $value) {

				if (!isset($item[$key])) {
					$include = false;
					break;
				}

				if ($item[$key] != $value) {
					$include = false;
					break;
				}

			}

			if ($include) $result[] = $item;
		}

		return $result;
	}

	/**
	 * Sorts the given array using the sorting for this datasource.
	 */
	protected function sortArray(&$ar) {

		if (empty($this->sorting)) {
			return;
		}

		usort($ar, array($this, 'compareItems'));
	}

	private function compareItems($x, $y) {

		foreach($this->sorting as $field => $asc) {

			$xVal = isset($x[$field]) ? $x[$field] : null;
			$yVal = isset($y[$field]) ? $y[$field] : null;

			$result = self::compareValues($xVal, $yVal);
			if (!$asc) $result *= -1;

			if ($result) {
				return $result;
			}
		}

		return 0;

	}

	private function compareValues($x, $y) {

		if ($x === $y) {
			return 0;
		}

		if (is_numeric($x) && is_numeric($y)) {
			return $x - $y;
		}

		return strcasecmp(trim($x), trim($y));
	}

}

