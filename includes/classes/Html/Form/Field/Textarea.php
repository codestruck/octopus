<?php

class Octopus_Html_Form_Field_Textarea extends Octopus_Html_Form_Field {


    public function __construct($type, $name, $label, $attributes = null) {
        $this->requireCloseTag = true;
        parent::__construct('textarea', $type, $name, $label, $attributes);
        $this->setAttribute('name', $name);
        $this->removeAttribute('type');
    }

    public function getAttribute($attr, $default = null) {

        if (strcasecmp($attr, 'value') == 0) {
            return $this->text();
        } else {
            return parent::getAttribute($attr, $default);
        }

    }

    public function setAttribute($attr, $value) {

        if (strcasecmp($attr, 'value') == 0) {
            return $this->text($value);
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

}

?>
