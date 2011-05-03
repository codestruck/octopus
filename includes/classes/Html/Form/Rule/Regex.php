<?php

Octopus::loadClass('Octopus_Html_Form_Rule');

class Octopus_Html_Form_Rule_Regex extends Octopus_Html_Form_Rule {

    private $pattern;

    public function __construct($pattern, $message) {
        parent::__construct($message);
        $this->pattern = $pattern;
    }

    public function getMessage($field, $data) {

        $message = parent::getMessage($field, $data);
        if ($message !== null) return $message;

        return "{field} does not match '{$this->pattern}'";
    }

    public function validate($field, $data) {

        $name = $field->name;
        if (empty($data[$name])) {
            return true;
        }

        $value = $data[$name];

        if (trim($value) == '') {
            return true;
        }

        return !!preg_match($this->pattern, $value);
    }

}

?>
