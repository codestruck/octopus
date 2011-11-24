<?php

/**
 * Basic interface for a data source to pass to, e.g. an Html_Table. Provides
 * a generic means of paging, filtering, and sorting sets of data.
 */
interface Octopus_DataSource extends Countable, Iterator {

	/**
	 * @return Octopus_DataSource A datasource derived from this one with a
	 * filter applied to the given field.
	 */
	function filter($field, $value);

	/**
	 * @return Octopus_DataSource A datasource derived from this one with
	 * all filters removed.
	 */
	function unfilter();

	/**
	 * @return Octopus_DataSource A datasource derived from this one with a
	 * new limit applied. This should not affect the number returned by
	 * count().
	 */
	function limit($start, $count = null);

	/**
	 * @return Octopus_DataSource A datasource derived from this one with all
	 * limits remove.
	 */
	function unlimit();

	/**
	 * @return Boolean Whether this datasource is sorted by the given field.
	 * @param String $field Field to check.
	 * @param Boolean $asc If the field is sorted, gets set to true if
	 * sorted ascending, or false if sorted descending.
	 * @param Number $index If this data source is sorted by more than 1 field,
	 * this gets sets to the index (zero-based) that $field is in the sort
	 * order.
	 */
	function isSortedBy($field, &$asc = null, &$index = 0);

	/**
	 * Sorts the datasource by the given field.
	 * @param String $field Field to sort by.
	 * @param Boolean $asc Whether to sort ascending (true) or descending
	 * (false)
	 * @param Boolean $replace Whether to replace any existing sorting on this
	 * datasource.
	 * @return Octopus_DataSource A datasource derived from this one with the
	 * new sorting applied.
	 */
	function sort($field, $asc = true, $replace = true);

	/**
	 * @return Octopus_DataSource A datasource derived from this one with all
	 * sorting removed.
	 */
	function unsort();

}

?>