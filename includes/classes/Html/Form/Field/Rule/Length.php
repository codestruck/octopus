<?php

/**
 * Validation rule used to ensure that the length of the input in a field is
 * within a range.
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_Html_Form_Field_Rule_Length extends Octopus_Html_Form_Field_Rule {

    private $minLength = null;
    private $maxLength = null;

    /**
     * @param Mixed $min Inclusive minimum number of characters, or null if there is no minimum.
     * @param Mixed $max Inclusive maximum number of characters, or null if there is no maximum.
     * @param Mixed $message Error message to display.
     */
    public function __construct($min, $max, $message) {
        parent::__construct($message);

        $this->minLength = $min;
        $this->maxLength = $max;
    }

    protected function doValidation($input, $field, $data) {

        $len = strlen($input);

        // TODO: would this be better like this:
        //
        //     if ($len === 0 && $field->isOptional())
        if ($len === 0) {
            return true;
        }

        return ($this->minLength === null || $len >= $this->minLength) &&
               ($this->maxLength === null || $len <= $this->maxLength);

    }

    protected function getDefaultMessage($field, $data) {

        $min = ($this->minLength === null ? null : number_format($this->minLength));
        $max = ($this->maxLength === null ? null : number_format($this->maxLength));

        $constraint = '';

        if ($min !== null && $max !== null) {
            $constraint = "between $min and $max characters";
        } else if ($min !== null) {
            $constraint = "at least " . plural_count($this->minLength, 'character');
        } else if ($max !== null) {
            $constraint = "at most " . plural_count($this->minLength, 'character');
        } else {
            return '';
        }

        return $field->getNiceName() . " must be $constraint.";
    }


}

