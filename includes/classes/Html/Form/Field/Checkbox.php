<?php

Octopus::loadClass('Octopus_Html_Form_Field');

class Octopus_Html_Form_Field_Checkbox extends Octopus_Html_Form_Field {

    public function __construct($type, $name, $label, $attributes = null) {
        parent::__construct('input', $type, $name, $label, $attributes);
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

    public function readValue(&$posted, &$values) {
        $values[$this->name] = !empty($posted[$this->name]);
    }

    public function val(/* $val */) {

        switch(func_num_args()) {

            case 0:
                return $this->checked();

            default:
                $checked = func_get_arg(0);
                return $this->checked($checked);

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