<?php

SG::loadClass('SG_Html_Element');

class SG_Html_Form_Field extends SG_Html_Element {

    public $label;
    public $input;
    public $help;

    private $_name;
    private $_type;

    public function __construct($name, $type = 'text', $attributes = null) {

        if (is_array($type) && $attributes === null) {
            $attributes = $type;
            $type = empty($attributes['type']) ? 'text' : $attributes['type'];
            unset($attributes['type']);
        }

        parent::__construct('div', $attributes);
        $this->id = $name . '_field';

        $this->_name = $name;
        $this->_type = $type;

        $this->addClass(array(strtolower($type), 'field'));

        $this->label = new SG_Html_Element('label', array('for' => $this->_name));
        $this->input = $this->createInput();

        $this->append($this->label);
        $this->append($this->input);
    }

    public function getLabel() {

        if ($this->label) {
            return $this->label->text();
        }

        return '';
    }

    public function setLabel($text) {

        if (!$this->label) {
            $this->label = new SG_Html_Element('label', array('for' => $this->_name));
        }

        $this->label->text($text);

    }

    public function setValue($value) {



    }

    /**
     * Creates the <input> element this field uses.
     */
    protected function createInput() {

        return new SG_Html_Element('input', array('type' => $this->_type, 'name' => $this->_name));


    }

}


?>
