<?php
/**
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_Html_Form_Field_Rule_Callback extends Octopus_Html_Form_Field_Rule {

    private $callback;

    public function __construct($callback, $message = null) {
        parent::__construct($message);
        $this->callback = $callback;
    }

    protected function doValidation($input, $field, $data) {
        return call_user_func($this->callback, $input, $data, $field);
    }

}

