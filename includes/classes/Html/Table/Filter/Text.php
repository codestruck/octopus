<?php

Octopus::loadClass('Octopus_Html_Table_Filter');

/**
 * A filter that is just a text box. Actual filter is done via a callback supplied
 * as an option.
 */
class Octopus_Html_Table_Filter_Text extends Octopus_Html_Table_Filter {


    protected function createElement() {

        $attribs = isset($this->options['attributes']) ? $this->options['attributes'] : null;

        $el = Octopus_Html_Form_Field::create('text', $this->id, $attribs);
        $el->name = $this->id;
        return $el;
    }

}

?>
