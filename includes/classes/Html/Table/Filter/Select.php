<?php

/**
 * Filter that displays a list of options.
 */
class Octopus_Html_Table_Filter_Select extends Octopus_Html_Table_Filter {

    protected function applyToResultSet($resultSet) {
        $val = $this->val();
        return $resultSet->where(array($this->id => $val));
    }

    protected function createElement() {
        $el = Octopus_Html_Form_Field::create('select', $this->id, $this->options);
        $el->name = $this->id;
        return $el;
    }

    protected function &initializeOptions(&$options) {

        if (is_array($options) && !isset($options['options'])) {
            // Allow passing just the options in
            $htmlOptions = $options;
            $options = array('options' => $htmlOptions);
        }

        $options = parent::initializeOptions($options);

        return $options;
    }

}

?>
