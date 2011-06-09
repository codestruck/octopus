<?php

Octopus::loadClass('Octopus_Html_Form_Field');

class Octopus_Html_Form_Field_Radio extends Octopus_Html_Form_Field {

    private $_valueFields = array('value', 'id', '/.*_id$/i');
    private $_textFields = array('name', 'title', 'desc', 'summary', 'description', 'text');

    protected $valueField = null;
    protected $textField = null;
    public $name;
    protected $isRequired = null;
    protected $options = array();

    public function __construct($type, $name, $label, $attributes = null) {
        parent::__construct('div', $type, $name, $label, $attributes);
        $this->removeAttribute('type');
        $this->removeAttribute('id');
        $this->removeAttribute('name');
        $this->requireCloseTag = true;

        $this->removeClass($this->name);
        $this->removeClass($type);

        $this->addClass(to_css_class($this->name . 'RadioGroup'));
        $this->addClass('radioGroup');

        $this->wrapperClass = to_css_class($this->name) . ' ' . to_css_class($type);
    }

    public function required($required = true) {
        parent::required($required);
        $this->isRequired = $required;
        $this->removeAttribute('required');
        return $this;
    }

    /**
     * Adds a single option to this select.
     * @return Object An Octopus_Html_Element for the option added.
     */
    public function addOption($value, $text = null, $attributes = null) {

        $opt = $this->createOption($value, $text, $attributes);
        $this->append($opt);
        return $opt;

    }

    /**
     * Adds multiple options to the select.
     * @param $options Array An array of options to add.
     * @return Object $this for method chaining.
     */
    public function addOptions($options) {

        if (empty($options)) {
            return $this;
        }

        if (is_string($options) || (is_array($options) && count($options) == 2 && is_callable($options))) {

            $options = call_user_func($options, $this);
            $this->addOptions($options);
            return $this;

        }

        $attributes = null;

        foreach($options as $value => $text) {

            if (is_numeric($value)) {

                if (is_array($text) || is_object($text)) {
                    $value = $text;
                    $text = null;
                }

            }

            $opt = $this->createOption($value, $text, null);
            $this->append($opt);
        }

        return $this;
    }

    /**
     * Helper function for setting the 'value' attribute.
     * @return Object $this for method chaining.
     */
    public function val(/* No args = return val, 1 arg = set value */) {

        $argCount = func_num_args();

        switch($argCount) {

            case 0:
                // uh?
                return null;

            default:
                $value = func_get_arg(0);
                if (is_array($value)) {
                    $value = array_pop($value);
                }
                $this->options[ $value ]->checked = true;
                return $this;
        }
    }

    public function &toArray() {

        $result = parent::toArray();
        $result['options'] = array();

        foreach($this->children() as $option) {
            $value = $option->getAttribute('value', null);
            if ($value === null) $value = $option->text();
            $result['options'][$value] = $option->text();
        }

        return $result;
    }

    /**
     * Factory method for creating <options>
     */
    protected function createOption($value, $text, $attributes) {

        if (is_array($text) && $attributes === null) {
            $attributes = $text;
            $text = null;
        }

        if (is_object($value)) {
            $this->getValueAndTextFromObject($value, $value, $text);
        } else if (is_array($value)) {
            $this->getValueAndTextFromArray($value, $value, $text);
        }

        if ($value !== null && $text === null) {
            $text = $value;
        } else if ($text !== null && $value === null) {
            $value = $text;
        }

        $divAttributes = $attributes;

        if ($attributes === null) $attributes = array();
        $attributes['value'] = $value;
        $attributes['type'] = 'radio';
        $attributes['id'] = to_css_class($this->name . 'Input' . $value);
        $attributes['name'] = $this->name;
        unset($attributes['class']);

        $opt = new Octopus_Html_Element('input', $attributes);

        $opt->addClass($this->name);
        $opt->addClass('radio');
        $opt->addClass(to_css_class('value' . $value));

        if ($this->isRequired) {
            $opt->addClass('required');
        }

        $label = new Octopus_Html_Element('label');
        $label->setAttribute('for', $opt->id);
        $label->text($text);

        $div = new Octopus_Html_Element('div');
        $div->addClass('radioItem');
        $div->addClass(to_css_class($this->name . 'RadioItem'));
        $div->addClass(to_css_class($this->name . $value . 'RadioItem'));

        if (isset($divAttributes['class'])) {
            $div->addClass(to_css_class($divAttributes['class']));
        }

        $this->options[ $value ] = $opt;

        $div->append($label);
        $div->append($opt);

        return $div;
    }

}

?>
