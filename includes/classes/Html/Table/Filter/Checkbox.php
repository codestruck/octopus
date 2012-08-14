<?php

/**
 * Simple checkbox filter
 * @todo test
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_Html_Table_Filter_Checkbox extends Octopus_Html_Table_Filter {

    protected function createElement() {
        $attribs = isset($this->options['attributes']) ? $this->options['attributes'] : null;
        $el = Octopus_Html_Form_Field::create('checkbox', $this->id, $attribs);
        $el->name = $this->id;
        return $el;
    }

}

