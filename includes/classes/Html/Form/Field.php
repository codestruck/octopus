<?php

SG::loadClass('SG_Html_Element');

class SG_Html_Form_Field extends SG_Html_Element {

    private static $_registry = array();

    private static $_formats = array(
        'email' => '/^\s*[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}\s*$/i'
    );

    public $label;
    public $help;

    private $_rules = array();
    private $_requiredRule = null;

    public function __construct($tag, $name, $type, $attributes) {

        parent::__construct($tag, $attributes);

        $this->type = $type;
        $this->name = $name;
        $this->id = $name . 'Input';

        $this->addClass(to_css_class($name))
             ->addClass(to_css_class($type));

        $this->label = $this->createLabel();

    }

    /**
     * Adds a validation rule to this field.
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
     * Sets the 'autofocus' attribute on this element.
     */
    public function autoFocus($focus = true) {

        if ($focus) {
            $this->setAttribute('autofocus', '');
        } else {
            $this->removeAttribute('autofocus');
        }

    }

    /**
     * Validates that input in this field is between two numbers.
     */
    public function between($inclusiveMin, $inclusiveMax, $message = null) {
        SG::loadClass('SG_Html_Form_Field_Rule_Range');
        return $this->addRule(new SG_Html_Form_Field_Rule_Range($inclusiveMin, $inclusiveMax, $message));
    }

    /**
     * Validates input against one of a known set of data formats, e.g.
     * email, zip code, etc.
     */
    public function mustBe($format, $message = null) {
        return $this->mustMatch(self::$_formats[$format], $message);
    }

    /**
     * Adds a regular expression rule to this field.
     */
    public function mustMatch($pattern, $message = null) {
        SG::loadClass('SG_Html_Form_Field_Rule_Regex');
        return $this->addRule(new SG_Html_Form_Field_Rule_Regex($pattern, $message));
    }

    /**
     * Adds a callback rule to this field.
     */
    public function mustPass($callback, $message = null) {
        SG::loadClass('SG_Html_Form_Field_Rule_Callback');
        return $this->addRule(new SG_Html_Form_Field_Rule_Callback($callback, $message));
    }

    /**
     * Marks this field as required.
     */
    public function required($required = true) {

        $message = (is_string($required) ? $required : null);
        $required = !!$required;

        $this->removeRule($this->_requiredRule);

        if ($required) {

            if (!$this->_requiredRule) {
                SG::loadClass('SG_Html_Form_Field_Rule_Required');
                $this->_requiredRule = new SG_Html_Form_Field_Rule_Required();
            }

            $this->_requiredRule->setMessage($message);
            $this->addRule($this->_requiredRule);
            $this->addClass('required');

        } else {
            $this->removeClass('required');
        }

        parent::setAttribute('required', $required);

        return $this;
    }

    /**
     * Helper function for setting the 'value' attribute.
     * @return Object $this for method chaining.
     */
    public function val(/* No args = return val, 1 arg = set value */) {

        $argCount = func_num_args();

        switch($argCount) {

            case 0:
                $val = $this->getAttribute('value');
                return $val;

            default:
                $this->setAttribute('value', func_get_arg(0));
                return $this;
        }
    }

    /**
     * Validates this field.
     * @param $data Array All data posted for the form.
     */
    public function validate($data) {

        $result = new StdClass();
        $result->errors = array();
        $errorCount = 0;

        foreach($this->_rules as $r) {

            $v = $r->validate($this, $data);

            if ($v === true) {
                continue;
            } else if ($v === false) {
                $result->errors[] = $r->getMessage($this, $data);
            } else if (is_string($v)) {
                $result->errors[] = $v;
            } else if (is_array($v)) {
                $result->errors += $v;
            }

            $errorCount++;
        }

        $result->success = !$errorCount;
        $result->hasErrors = !!$errorCount;

        return $result;
    }

    public function setAttribute($attr, $value) {

        if (strcasecmp($attr, 'required') == 0) {
            return $this->required($value);
        } else {
            return parent::setAttribute($attr, $value);
        }

    }

    /**
     * Creates the <label> element used by this field.
     */
    protected function createLabel() {

        $label = new SG_Html_Element('label', array('for' => $this->name));
        $label->text(humanize($this->name) . ':');

        return $label;
    }

    /**
     * Registers a form field type.
     * @param $name String The unique name of this field type.
     * @param $class String The name of the class used for this field type
     * @param $attributes Array Default attributes for this input type.
     * @param $tag String The tag this type uses, if $class is SG_Html_Element.
     */
    public static function register($name, $class, $attributes = null) {

        self::$_registry[$name] = array(
            'class' => $class,
            'attributes' => $attributes,
        );

    }

    /**
     * Factory method for creating new form fields.
     */
    public static function create($name, $type = null, $desc = null, $attributes = null) {

        if (is_array($desc) && $attributes === null) {
            $attributes = $desc;
            $desc = null;
        }

        if (is_array($type) && $desc === null && $attributes === null) {
            $attributes = $type;
            $type = empty($attributes['type']) ? 'text' : $attributes['type'];
            unset($attributes['type']);
        }

        $attributes = $attributes ? $attributes : array();

        if ($type === null) {
            $type = empty($attributes['type']) ? $name : $attributes['type'];
        }

        $class = 'SG_Html_Form_Field';

        if (isset(self::$_registry[$type])) {
            $entry = self::$_registry[$type];
            $class = empty($entry['class']) ? $class : $entry['class'];
            $attributes = array_merge(empty($entry['attributes']) ? array() : $entry['attributes'], $attributes);
        }


        SG::loadClass($class);

        if ($class == 'SG_Html_Form_Field') {
            return new SG_Html_Form_Field('input', $name, $type, $attributes);
        } else {
            return new $class($name, $type, $attributes);
        }
    }

}

SG_Html_Form_Field::register('email', 'SG_Html_Form_Field', array('type' => 'email', 'class' => 'text'));

?>
