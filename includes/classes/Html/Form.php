<?php

SG::loadClass('SG_Html_Element');

class SG_Html_Form extends SG_Html_Element {

    public function __construct($id, $attributes = null) {

        $attributes = $attributes ? $attributes : array();
        $attributes['id'] = $id;

        parent::__construct('form', $attributes);
    }

    public function &add($nameOrElement, $type = 'text', $desc = null, $attributes = null) {

        if ($nameOrElement instanceof SG_Html_Element) {
            $this->append($nameOrElement);
            return $nameOrElement;
        }

        // TODO factory for creating fields

    }

    public function setData($data) {
    }

}

?>
