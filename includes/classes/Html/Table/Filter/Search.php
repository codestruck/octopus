<?php

/**
 * TextBox filter that uses Octopus_Model free text searching.
 */
class Octopus_Html_Table_Filter_Search extends Octopus_Html_Table_Filter_Text {

    protected function applyToResultSet($resultSet) {

        $val = $this->val();
        if (!$val) {
            return $resultSet;
        }

        return $resultSet->matching($val);
    }

    protected function createElement() {

        $el = parent::createElement();
        $el->type = 'search';

        return $el;
    }

}

?>
