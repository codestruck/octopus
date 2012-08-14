<?php

/**
 * A single restriction used by a result set when filtering.
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
interface Octopus_Model_Restriction {

    /**
     * @param $s Octopus_DB_Select being constructed.
     * @param $params Array of parameters to be used by $s.
     * @return String SQL for use in the WHERE clause being generated.
     */
    function getSql(Octopus_DB_Select $s, Array &$params);

}

