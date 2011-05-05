<?php

Octopus::loadClass('Octopus_Html_Form_Field');

class Octopus_Html_Form extends Octopus_Html_Element {

    private $_rules = array();
    private $_values = null;

    /**
     * Custom template to use when rendering this form.
     */
    public $template = null;

    public function __construct($id, $attributes = null) {

        if (is_string($attributes)) {
            $attributes = array('method' => $attributes);
        }

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

    /**
     * @return Array The set of values posted for this form.
     */
    public function getValues() {

        if ($this->_values !== null) {
            return $this->_values;
        }

        $method = strtolower($this->getAttribute('method', 'get'));

        switch($method) {

            case 'get':
                $this->_values = $_GET;
                break;

            case 'post':
                $this->_values = $_POST;
                break;
        }


        return $this->_values;
    }

    /**
     * Sets the data in this form.
     */
    public function setValues($values) {
        $this->_values = $values;
    }

    public function mustPass($callback, $message = null) {
        Octopus::loadClass('Octopus_Html_Form_Rule_Callback');
        return $this->addRule(new Octopus_Html_Form_Rule_Callback($callback, $message));
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
