<?php

Octopus::loadClass('Octopus_Html_Table_Filter');

/**
 * A filter that is just a text box. Actual filter is done via a callback supplied
 * as an option.
 */
class Octopus_Html_Table_Filter_Text extends Octopus_Html_Table_Filter {

    protected function applyToResultSet($resultSet) {

        $val = $this->val();
        if ($val) {
            $val = wildcardify($val);
        }

        return $resultSet->where(array("{$this->id} LIKE" => $val));
    }

    protected function createElement() {
        $el = Octopus_Html_Form_Field::create('text', $this->id, $this->options);
        $el->name = $this->id;
        return $el;
    }

}

?>
