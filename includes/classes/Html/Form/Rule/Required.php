<?php

Octopus::loadClass('Octopus_Html_Form_Rule');

/**
 * 'Required' rule.
 */
class Octopus_Html_Form_Rule_Required extends Octopus_Html_Form_Rule {

    public function validate($field, $data) {
        $val = isset($data[$field->name]) ? trim($data[$field->name]) : '';

        return $val !== '';
    }

}

?>
