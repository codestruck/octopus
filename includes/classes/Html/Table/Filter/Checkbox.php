<?php

Octopus::loadClass('Octopus_Html_Table_Filter');

/**
 * Simple checkbox filter
 * @TODO test
 */
class Octopus_Html_Table_Filter_Checkbox extends Octopus_Html_Table_Filter {

    protected function createElement() {
        $attribs = isset($this->options['attributes']) ? $this->options['attributes'] : null;
        $el = Octopus_Html_Form_Field::create('checkbox', $this->id, $attribs);
        $el->name = $this->id;
        return $el;
    }

}

?>
