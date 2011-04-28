<?php

SG::loadClass('SG_Html_Form_Rule');

/**
 * 'Required' rule.
 */
class SG_Html_Form_Rule_Required extends SG_Html_Form_Rule {

    public function validate($field, $data) {
        $val = isset($data[$field->name]) ? trim($data[$field->name]) : '';

        return $val !== '';
    }

}

?>
