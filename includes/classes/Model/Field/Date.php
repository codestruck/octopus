<?php

/**
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_Model_Field_Date extends Octopus_Model_Field_Datetime {

    public function __construct($field, $modelClass, $options) {
        parent::__construct($field, $modelClass, $options, 'newDate');
        $this->defaultOptions['date_format'] = 'Y-m-d';
    }

}
