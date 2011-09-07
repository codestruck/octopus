<?php

/**
 * Basic interface for a data source to pass to, e.g. an Html_Table.
 */
interface Octopus_DataSource {
	
	/**
	 * @return A new DataSource with the given filter applied.
	 */
	function filter($field, $value);

	/**
	 * @return Iterator of at most $count items starting at $start.
	 * @param $start Number Start index (zero-based)
	 * @param $count Number Max number of items to fetch. Zero for all items.
	 */
	function getItems($start = 0, $count = 0);

	/**
	 * @return A new DataSource sorted in the given way.
	 */
	function sort($field, $asc = true);

}

?>