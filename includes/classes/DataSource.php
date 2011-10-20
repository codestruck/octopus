<?php

/**
 * Basic interface for a data source to pass to, e.g. an Html_Table.
 */
interface Octopus_DataSource extends Countable {

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
	 * @return A new Octopus_DataSource that is a subset of this datasource.
	 */
	function limit($start, $count);

	/**
	 * Undoes a call to limit().
	 * @return Octopus_DataSource
	 */
	function unlimit();

	/**
	 * @return A new DataSource sorted in the given way.
	 */
	function sort($field, $asc = true);

}

?>