<?php

class Octopus_Html_Form_Field_Rule_Regex extends Octopus_Html_Form_Field_Rule {

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

    public function doValidation($input, $field, $data) {

        return !!preg_match($this->pattern, $input);

    }

}

?>
