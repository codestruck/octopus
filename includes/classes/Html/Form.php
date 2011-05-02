<?php

SG::loadClass('SG_Html_Form_Field');

class SG_Html_Form extends SG_Html_Element {

    private $_rules = array();

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
     * Adds a validation rule to this form.
     */
    public function addRule($rule) {

        if (!$rule) {
            return $this;
        }

        $this->_rules[] = $rule;
        return $this;
    }

    public function getRules() {
        return $this->_rules;
    }

    public function removeRule($rule) {

        if (!$rule) {
            return $this;
        }

        $newRules = array();

        foreach($this->_rules as $r) {

            if ($rule !== $r) {
                $newRules[] = $r;
            }
        }

        $this->_rules = $newRules;

        return $this;

    }

    public function mustPass($callback, $message = null) {
        SG::loadClass('SG_Html_Form_Rule_Callback');
        return $this->addRule(new SG_Html_Form_Rule_Callback($callback, $message));
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

        foreach($this->_rules as $r) {

            $ruleResult = $r->validate($this, $values);

            if ($ruleResult === true) {
                continue;
            } else if ($ruleResult === false) {
                $result->errors[] = $r->getMessage($this, $values);
            } else {
                $result->errors += $ruleResult;
            }

        }

        $result->success = (count($result->errors) == 0);
        $result->hasErrors = !$result->success;

        return $result;

    }

    private function validateRecursive(&$el, &$values, &$result) {

        if ($el instanceof SG_Html_Form_Field) {

            $fieldResult = $el->validate($values);
            $result->errors += $fieldResult->errors;

        }

        foreach($el->children() as $c) {
            $this->validateRecursive($c, $values, $result);
        }

    }


}

?>
