<?php

SG::loadClass('SG_Html_Form_Field_Rule');

/**
 * 'Required' rule.
 */
class SG_Html_Form_Field_Rule_Required extends SG_Html_Form_Field_Rule {

    public function validate($field, $data) {
        return trim($this->getInput($field, $data)) !== '';
    }

}

?>
