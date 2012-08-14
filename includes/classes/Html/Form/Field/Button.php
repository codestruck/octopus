<?php

/**
 * Field subclass used for buttons.
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_Html_Form_Field_Button extends Octopus_Html_Form_Field {

    protected function createWrapper($tag = null) {
        return null;
    }

    protected function createLabel() {
        return null;
    }

    protected function createHelpLabel() {
        return null;
    }

}

Octopus_Html_Form_Field::register('button', 'Octopus_Html_Form_Field_Button');
Octopus_Html_Form_Field::register('submit', 'Octopus_Html_Form_Field_Button', array('type' => 'submit'));
Octopus_Html_Form_Field::register('reset', 'Octopus_Html_Form_Field_Button', array('type' => 'reset'));

