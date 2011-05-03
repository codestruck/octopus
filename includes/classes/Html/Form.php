<?php

Octopus::loadClass('Octopus_Html_Form_Field');

class Octopus_Html_Form extends Octopus_Html_Element {

    public function __construct($id, $attributes = null) {

        $attributes = $attributes ? $attributes : array();
        $attributes['id'] = $id;

        parent::__construct('form', $attributes);
    }

    public function &add($nameOrElement, $type = 'text', $desc = null, $attributes = null) {

        $field = null;

        if ($nameOrElement instanceof Octopus_Html_Element) {
            $field = $nameOrElement;
        } else {
            $field = Octopus_Html_Form_Field::create($nameOrElement, $type, $desc, $attributes);
        }

        if ($field) {
            $this->append($field);
        }

        return $field;
    }

    /**
     * Validates data in this form.
     * @param $values Array Data to validate. If not specified, then either
     * $_GET or $_POST will be used as appropriate.
     * @return Object An object with two properties: success and errors.
     */
    public function validate($values = null) {

        $values = ($values === null ? $_POST : $values);

        $result = new StdClass();
        $result->errors = array();

        foreach($this->children() as $c) {
            $this->validateRecursive($c, $values, $result);
        }

        $result->success = (count($result->errors) == 0);
        $result->hasErrors = !$result->success;

        return $result;

    }

    private function validateRecursive(&$el, &$values, &$result) {

        if ($el instanceof Octopus_Html_Form_Field) {

            $fieldResult = $el->validate($values);
            $result->errors += $fieldResult->errors;

        }

        foreach($el->children() as $c) {
            $this->validateRecursive($c, $values, $result);
        }

    }


}

?>
