<?php

class Octopus_Html_Form_Field_Static extends Octopus_Html_Form_Field {

    public function __construct($type, $name, $label, $attributes = null) {

        $val = '';

        if (is_string($attributes)) {
            $val = $attributes;
            $attributes = null;
        }

        parent::__construct('span', $type, $name, $label, $attributes);
        $this->removeAttribute('type');

        $this->val($val);
    }

    public function getAttribute($attr, $default = null) {

        if (strcasecmp($attr, 'value') == 0) {
            return $this->html();
        } else {
            return parent::getAttribute($attr, $default);
        }

    }

    public function setAttribute($attr, $value) {

        if (strcasecmp($attr, 'value') == 0) {
            return $this->html($value);
        } else {
            return parent::setAttribute($attr, $value);
        }

    }

}

?>
