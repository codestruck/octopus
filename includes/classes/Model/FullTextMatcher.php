<?php

/**
 * A base for implementing logic to filter and sort Octopus_Model instances
 * based on free text input.
 */
abstract class Octopus_Model_FullTextMatcher {

    /**
     * Applies a free-text query to a result set and returns a filtered result
     * set.
     * @param  Octopus_Model_ResultSet $resultSet
     * @param  String $query
     * @return Octopus_Model_ResultSet
     */
    abstract public function filter(Octopus_Model_ResultSet $resultSet, $query);

}