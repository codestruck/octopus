<?php

Octopus::loadClass('Octopus_DataSource');

class Octopus_DataSource_Array implements Octopus_DataSource {
	
	private $parent;
	private $array;

	private $sorted = false;
	private $sortField = null;
	private $sortAsc = true;

	public function __construct(Array $array, $parent = null) {
		$this->parent = $parent;
		$this->array = $array;
	}

	/**
	 * @return A new DataSource with the given filter applied.
	 */
	public function filter($field, $value) {
		
		$filteredArray = array();
		foreach($this->array as $item) {
			if (isset($item[$field])) {
				
				if ($item[$field] == $value) {
					$filteredArray[] = $item;
				}

			}
		}

		$result = new Octopus_DataSource_Array($filteredArray, $this);
		$result->sorted = $this->sorted;
		$result->sortField = $this->sortField;
		$result->sortAsc = $this->sortAsc;

		return $result;
	}

	/**
	 * @return Iterator of at most $count items starting at $start.
	 * @param $start Number Start index (zero-based)
	 * @param $count Number Max number of items to fetch
	 */
	public function getItems($start = 0, $count = 0) {
		
		if ($this->sortField && !$this->sorted) {
			// Sort!
			usort($this->array, array($this, $this->sortAsc ? 'compareItems' : 'compareItemsInverted'));
			$this->sorted = true;
		}

		if ($count == 0) {
			$count = count($this->array); 
		}

		$result = array();
		$index = 0;
		$currentCount = 0;

		foreach($this->array as $item) {

			if ($currentCount >= $count) {
				break;
			}

			if ($index >= ($start + $count)) {
				break;
			} else if ($index >= $start) {
				$result[] = $item;
				$currentCount++;
			}

			$index++;
		}

		return $result;
	}

	/**
	 * @return A new DataSource sorted in the given way.
	 */
	public function sort($field, $asc = true) {
		
		if (is_string($asc)) {
			if (strcasecmp($asc, 'asc') === 0) {
				$asc = true;
			} else if (strcasecmp($asc, 'desc') === 0) {
				$asc = false;
			}
		}

		$result = new Octopus_DataSource_Array($this->array, $this);
		$result->sorted = false;
		$result->sortField = $field;
		$result->sortAsc = $asc;

		return $result;
	}

	private function compareItemsInverted($x, $y) {
		return $this->compareItems($y, $x);
	}

	private function compareItems($x, $y) {
		
		$x = isset($x[$this->sortField]) ? $x[$this->sortField] : null;
		$y = isset($y[$this->sortField]) ? $y[$this->sortField] : null;

		if ($x === null && $y === null) {
			return false;
		} else if ($x === null) {
			return -1; 
		} else if ($y === null) {
			return 1;
		}

		if (is_string($x) || is_string($y)) {
			return strcasecmp($x, $y);
		}

		return $x - $y;
	}

}

?>