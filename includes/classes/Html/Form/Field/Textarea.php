<?php

Octopus::loadClass('Octopus_Html_Form_Field');

class Octopus_Html_Form_Field_Textarea extends Octopus_Html_Form_Field {


    public function __construct($type, $name, $label, $attributes = null) {
        $this->requireCloseTag = true;
        parent::__construct('textarea', $type, $name, $label, $attributes);
        $this->setAttribute('name', $name);
        $this->removeAttribute('type');
    }

    public function getAttribute($attr, $default = null) {

        if (strcasecmp($attr, 'value') == 0) {
            return $this->getValue();
        } else {
            return parent::getAttribute($attr, $default);
        }

    }

    public function setAttribute($attr, $value) {

        if (strcasecmp($attr, 'value') == 0) {
            return $this->setValue($value);
        } else {
            return parent::setAttribute($attr, $value);
        }

    }

    public function &toArray() {

        $result = parent::toArray();

        $value = $this->val();
        if ($value === null) $value = '';

        $result['value'] = htmlspecialchars($value);

        return $result;
    }

    private function getValue() {
        return $this->text();
    }

    private function setValue($value) {

        $this->text($value);
        return $this;

    }

}

?>
