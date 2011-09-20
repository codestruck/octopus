<?php

Octopus::loadClass('Octopus_Html_Form_Field');

class Octopus_Html_Form_Field_Checkbox extends Octopus_Html_Form_Field {

    public function __construct($type, $name, $label, $attributes = null) {
        parent::__construct('input', $type, $name, $label, $attributes);

        if (substr($name, -2) === '[]') {
            $cssName = substr($name, 0, -2);
            $this->class = to_css_class($cssName);
            $this->id = to_css_class($cssName . ucfirst($attributes['value']) . 'Input');
            $this->wrapperId = to_css_class($cssName . ucfirst($attributes['value']) . 'Field');
            $this->wrapperClass = $this->class . ' ' . to_css_class('value' . $attributes['value']) . ' ' . $type;

            $this->addClass('value' . $attributes['value'])->addClass($type);
        }

    }

    public function required($required = true) {
        parent::required($required);

        // don't set attribute to prevent incorrect browser validation
        $this->removeAttribute('required');
        return $this;
    }

    public function checked(/* $checked */) {

        switch(func_num_args()) {

            case 0:
                return !!$this->getAttribute('checked');

            default:
                $checked = func_get_arg(0);
                if ($checked) {
                    return $this->setAttribute('checked', true);
                } else {
                    return $this->removeAttribute('checked');
                }
        }

    }

    public function val(/* $val */) {

        switch(func_num_args()) {

            case 0:
                return $this->checked();

            default:
                $values = func_get_arg(0);

                if (substr($this->name, -2) === '[]') {
                    $on = false;
                    if (is_array($values)) {
                        $on = in_array($this->value, $values);
                    } else if (is_object($values)) {
                        $on = count($values->where(array('id', $this->value)));
                    }
                    return $this->checked($on);
                } else {
                    return $this->checked($values);
                }
        }

    }

    protected function valueChanged() {

        parent::valueChanged();

        $val = $this->getAttribute('value');
        $this->removeAttribute('value');

        $this->checked($val);
    }

}

?>
