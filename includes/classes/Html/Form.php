<?php

SG::loadClass('SG_Html_Form_Field');

class SG_Html_Form extends SG_Html_Element {

    public function __construct($id, $attributes = null) {

        $attributes = $attributes ? $attributes : array();
        $attributes['id'] = $id;

        parent::__construct('form', $attributes);
    }

    public function &add($nameOrElement, $type = 'text', $desc = null, $attributes = null) {

        $field = null;

        if ($nameOrElement instanceof SG_Html_Element) {
            $field = $nameOrElement;
        } else {
            $field = SG_Html_Form_Field::create($nameOrElement, $type, $desc, $attributes);
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

        $result = new StdClass();
        $result->success = true;

        return $result;
    }


}

?>
