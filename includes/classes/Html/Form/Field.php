<?php

/**
 * A field on a form.
 */
class Octopus_Html_Form_Field extends Octopus_Html_Element {

    private static $_registry = array();

    /**
     * Functions and regexes used for mustBe.
     */
    private static $_formats = array(
        'email' => array(
            'function' => array('Octopus_Html_Form_Field', 'validateEmail'),
        )
    );

    public $help = null;
    public $wrapper = null;

    private $_rules = array();
    private $_requiredRule = null;

    private $_label = null;
    private $_labelElements = array();
    private $_niceName = null;

    private $_longDesc = '';
    private $_longDescLabelElements = array();

    private $_validationResult = null;

    public function __construct($tag, $type, $name, $label, $attributes) {

        parent::__construct($tag, $attributes);

        $this->type = $type;
        $this->name = $name;
        $this->id = $name . 'Input';

        if ($label === null) {
        	$label = $this->getDefaultLabel($name);
        }

        $this->addClass(to_css_class($name), to_css_class($type))
             ->label($label);
    }

    public function addClass() {
        $args = func_get_args();

        if ($this->wrapper) {
            $this->wrapper->addClass($args);
        }

        return parent::addClass($args);
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
     * Makes this field aware of a <label> element for it.
     */
    public function addLabel($label) {
        $this->_labelElements[] = $label;
        $this->updateLabels();
        return $this;
    }

    /**
     * Makes this field aware of a <label> used for its long description.
     */
    public function addLongDescriptionLabel($label) {
        $this->_longDescLabelElements[] = $label;
        $this->updateLongDescLabels();
        return $this;
    }

    /**
     * Sets the 'autofocus' attribute on this element.
     */
    public function autoFocus($focus = true) {

        if ($focus) {
            return $this->setAttribute('autofocus', true);
        } else {
            return $this->removeAttribute('autofocus');
        }
    }

    /**
     * Validates that input in this field is between two numbers.
     */
    public function between($inclusiveMin, $inclusiveMax, $message = null) {
        return $this->addRule(new Octopus_Html_Form_Field_Rule_Range($inclusiveMin, $inclusiveMax, $message));
    }

    /**
     * Gets/sets the label for this attribute.
     */
    public function label(/* $label */) {

        switch(func_num_args()) {

            case 0:
                return $this->_label;

            default:

                $this->_label = func_get_arg(0);
                $this->updateLabels();

                return $this;
        }

    }

    public function getLongDescription() {
        return $this->_longDesc;
    }

    public function setLongDescription($text) {
        $this->_longDesc = $text;
        $this->updateLongDescLabels();
    }

    public function getForm() {
        return $this->closest('form');
    }

    /**
     * Validates that input in this field is at most $length characters long.
     */
    public function maxLength($length, $message = null) {
    	return $this->addRule(new Octopus_Html_Form_Field_Rule_Length(null, $length, $message));
    }

    /**
     * Validates that input in this field is at least $length characters long.
     * Note: To catch zero-length input, use required() in addition to
     * minLength().
     */
    public function minLength($length, $message = null) {
    	return $this->addRule(new Octopus_Html_Form_Field_Rule_Length($length, null, $message));
    }

    /**
     * Validates input against one of a known set of data formats, e.g.
     * email, zip code, etc.
     */
    public function mustBe($format, $message = null) {

        $entry = self::$_formats[$format];

        if (is_array($entry)) {

            if (isset($entry['function'])) {
                return $this->mustPass($entry['function'], $message);
            } else if (isset($entry['pattern'])) {
                return $this->mustMatch($entry['pattern'], $message);
            }

        } else if (is_string($entry)) {

            if (parse_regex($entry)) {
                return $this->mustMatch($entry, $message);
            } else if (is_callable($entry)) {
                return $this->mustPass($entry, $message);
            }
        }

        throw new Octopus_Exception("Invalid mustBe format: " . $format);
    }

    /**
     * Adds a rule for matching this field against a regular expression or
     * another field.
     * @param $patternOrFieldName String Either a regular expression OR the
     * name of another field on this form that this field's value must match.
     * @param $message String Error message to show if validation fails.
     */
    public function mustMatch($patternOrFieldName, $message = null) {

        if (parse_regex($patternOrFieldName)) {
            $rule = new Octopus_Html_Form_Field_Rule_Regex($patternOrFieldName, $message);
        } else {
            $rule = new Octopus_Html_Form_Field_Rule_MatchField($patternOrFieldName, $message);
        }

        return $this->addRule($rule);
    }

    /**
     * Adds a callback rule to this field.
     */
    public function mustPass($callback, $message = null) {
        $rule = new Octopus_Html_Form_Field_Rule_Callback($callback, $message);
        return $this->addRule($rule);
    }

    /**
     * Gets/sets the "nice" name of this field. This is the name used to
     * refer to the field in error messages, etc. By default, this is the
     * same as label(), but with any trailing colons removed.
     */
    public function niceName(/* $name */) {

    	$args = func_get_args();
    	if (count($args) === 0) {
    		return $this->getNiceName();
    	} else {
    		return $this->setNiceName($args[0]);
    	}

    }

    /**
     * @return String A nice name you can use to refer to this field in e.g.,
     * error messages.
     */
    public function getNiceName() {

    	if ($this->_niceName !== null) {
    		return $this->_niceName;
    	}

    	return preg_replace('/:\s*$/', '', $this->label());
    }

    /**
     * @param Mixed $name New nice name to set. If null, the nice name will be
     * adapted from the current label.
     * @return Octopus_Html_Form_Field This field, for method chaining.
     */
    public function setNiceName($name) {
    	$this->_niceName = $name;
    	return $this;
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
                $this->_requiredRule = new Octopus_Html_Form_Field_Rule_Required($this);
            }

            $this->_requiredRule->setMessage($message);
            $this->addRule($this->_requiredRule);
            $this->addClass('required');
            $this->wrapper->addClass('required');

        } else {
            $this->removeClass('required');
            $this->wrapper->removeClass('required');
        }

        parent::setAttribute('required', $required);

        return $this;
    }

    public function isRequired() {

    	if ($this->required) {
    		return true;
    	}

    	foreach($this->_rules as $rule) {
    		if ($rule instanceof Octopus_Html_Form_Field_Rule_Required) {
    			return true;
    		}
    	}

    	return false;
    }

    /**
     * @return Array An array of details about this field suitable for use
     * in rendering via a template.
     */
    public function &toArray() {

        $result = array();
        Octopus_Html_Form::attributesToArray($this->getAttributes(), $result);

        $result['html'] = trim($this->render(true));

        if ($this->_validationResult) {
            $result['valid'] = $this->_validationResult->success;
            $result['errors'] = $this->_validationResult->errors;
        } else {
            $result['valid'] = true;
            $result['errors'] = array();
        }

        $label = $this->label();
        if ($label) {
            $result['label'] = array(
                'text' => $label
            );
            foreach($this->_labelElements as $l) {
                $result['label']['html'] = trim($l->render(true));
                break;
            }
        }

        if ($this->wrapper) {

            $result['full_html'] = trim($this->wrapper->render(true));
            $result['wrapper'] = array(
            	'open_tag' => $this->wrapper->renderOpenTag() . '>',
            	'close_tag' => $this->wrapper->renderCloseTag('foo'),
	        );

        } else {
            $result['full_html'] = $result['html'];
        }


        return $result;
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
                $value = func_get_arg(0);
                $this->setAttribute('value', $value);
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

            // true or non-zero = pass
            if ($v === true || is_numeric($v) && $v != 0) {
                continue;
            }

            $errorCount++;

            if ($v === false || $v === 0) {
                $result->errors[] = $r->getMessage($this, $data);
            } else if (is_string($v)) {
                $result->errors[] = $v;
            } else if (is_array($v)) {
                $result->errors += $v;
            }

            break;

        }

        $result->success = !$errorCount;
        $result->hasErrors = !!$errorCount;

        $this->_validationResult = $result;

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
     * Given an array of posted values, intializes this field.
     */
    public function loadValue(&$values) {

    	$name = preg_replace('/\[\]$/', '', $this->name);
		$value = isset($values[$name]) ? $values[$name] : null;

		$this->val($value);

        return $this;

    }

    /**
     * Reads this field's value into a final array of values.
     * @param $values Array Array being populated w/ form data.
     */
    public function readValue(&$values) {

    	$name = preg_replace('/\[\]$/', '', $this->name);
        $values[$name] = $this->val();

        return $this;
    }

    /**
     * Creates a wrapper/label structure for this field, adds the field to
     * it in the correct place, and returns the wrapper.
     */
    public function wrap() {

		if ($this->type == 'hidden') {
			// Hidden inputs don't get wrapped
            return $this;
        }

        $label = new Octopus_Html_Element('label');
        $this->addLabel($label);

        $wrapper = $this->createWrapper();
        if (!$wrapper) return $this;

        if ($this->type == 'checkbox') {

            // Checkboxes are usually like [x] Label rather than Label [x]
            $wrapper->append($this);
            $wrapper->append($label);

        } else {
            $wrapper->append($label);
            $wrapper->append($this);
        }

        return $wrapper;
    }

    /**
     * @return Octopus_Html_Element An empty html element to be used to wrap
     * this field, or null if this field should not be wrapped.
     */
    protected function createWrapper($tag = null) {

    	if ($tag === null) $tag = 'div';

		$wrapper = new Octopus_Html_Element($tag);
        $wrapper->id = preg_replace('/\[\]$/', '', $this->name) . 'Field';
        $wrapper->addClass('field', $this->class);

        return $wrapper;
    }

    /**
     * @param String $name Name to use to generate a default label.
     * @return String Default text to put in this control's label.
     */
    protected function getDefaultLabel($name) {

    	return humanize($name) . ':';

    }

    /**
     * Sets the text of any labels that have been added to this field. This
     * is called automatically when you call the label() method.
     */
    protected function updateLabels() {

    	$text = $this->_label;

        foreach($this->_labelElements as $l) {
            $l->setAttribute('for', $this->id)
              ->html($text ? $text : '');
        }

    }

    protected function updateLongDescLabels() {

        $text = $this->_longDesc;

        // HACK: this is ugly, but most fields won't have a long description,
        // so only add one if needed.

        if ($text && $this->wrapper && empty($this->_longDescLabelElements)) {
            $label = new Octopus_Html_Element('div');
            $this->wrapper->append($label);
            $this->_longDescLabelElements[] = $label;
        }

        foreach($this->_longDescLabelElements as $l) {
            $l->addClass('fieldDescription')
              ->text($text);
        }
    }

    protected function attributeChanged($attr, $oldValue, $newValue) {

        parent::attributeChanged($attr, $oldValue, $newValue);

        if (strcasecmp($attr, 'value') == 0) {
            $this->valueChanged();
        }
    }

    /**
     * Hook that's called whenever this field's value changes.
     */
    protected function valueChanged() {
        $this->_validationResult = null;
    }

    /**
     * Registers a form field type.
     * @param $name String The unique name of this field type.
     * @param $class String The name of the class used for this field type
     * @param $attributes Array Default attributes for this input type.
     * @param $tag String The tag this type uses, if $class is Octopus_Html_Element.
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
    public static function create($type, $name = null, $label = null, $attributes = null) {

        if ($type && ($name === null && $label === null && $attributes === null)) {
            // create([implied 'text'], $type);
            $name = $type;
            if (!isset(self::$_registry[$type])) $type = 'text';
        }

        if (is_array($label) && $attributes === null) {
            // create($type, $name, $attributes)
            $attributes = $label;
            $label = null;
        }

        if (is_array($name) && $label === null && $attributes === null) {
            // create($type, $attributes)
            $attributes = $name;
        }

        if (!$attributes) $attributes = array();

        if ($name === null) {
            $name = isset($attributes['name']) ? $attributes['name'] : $type;
            unset($attributes['name']);
        }

        unset($attributes['label']);

        $class = 'Octopus_Html_Form_Field';

        if (isset(self::$_registry[$type])) {
            $entry = self::$_registry[$type];
            $class = empty($entry['class']) ? $class : $entry['class'];
            $attributes = array_merge(empty($entry['attributes']) ? array() : $entry['attributes'], $attributes);
        }

        if ($class == 'Octopus_Html_Form_Field') {
            return new Octopus_Html_Form_Field('input', $type, $name, $label, $attributes);
        } else {
            return new $class($type, $name, $label, $attributes);
        }
    }

    /**
     * Helper function used for mustBe('email');
     */
    public static function validateEmail($input) {
        $input = trim($input);
        if (!$input) return true;
        return is_email($input);
    }

}

Octopus_Html_Form_Field::register('hidden', 'Octopus_Html_Form_Field', array('type' => 'hidden', 'class' => ''));
Octopus_Html_Form_Field::register('email', 'Octopus_Html_Form_Field', array('type' => 'email', 'class' => 'text'));
Octopus_Html_Form_Field::register('password', 'Octopus_Html_Form_Field', array('type' => 'password', 'class' => 'text'));
Octopus_Html_Form_Field::register('textarea', 'Octopus_Html_Form_Field_Textarea');
Octopus_Html_Form_Field::register('select', 'Octopus_Html_Form_Field_Select');
Octopus_Html_Form_Field::register('radio', 'Octopus_Html_Form_Field_Radio');
Octopus_Html_Form_Field::register('checkbox', 'Octopus_Html_Form_Field_Checkbox');
Octopus_Html_Form_Field::register('static', 'Octopus_Html_Form_Field_Static');

?>
