<?php

Octopus::loadClass('Octopus_Html_Form_Field');

class Octopus_Html_Form_Field_Textarea extends Octopus_Html_Form_Field {

    private $_valueFields = array('value', 'id', '/.*_id$/i');
    private $_textFields = array('name', 'title', 'desc', 'summary', 'description', 'text');

    protected $valueField = null;
    protected $textField = null;

    public function __construct($name, $type = null, $attributes = null) {
        $this->requireCloseTag = true;
        parent::__construct('textarea', $name, $type ? $type : 'textarea', $attributes);
        $this->setAttribute('name', $name);
        $this->removeAttribute('type');
    }

    public function setAttribute($attr, $value) {

        if (strcasecmp($attr, 'value') == 0) {
            return $this->setValue($value);
        } else {
            return parent::setAttribute($attr, $value);
        }

    }

    private function getValue() {
        return $this->text();
    }

    private function setValue($value) {

        $this->text($value);
        return $this;

    }

}

Octopus_Html_Form_Field::register('textarea', 'Octopus_Html_Form_Field_Textarea');

?>
