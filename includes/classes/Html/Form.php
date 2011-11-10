<?php

class Octopus_Html_Form extends Octopus_Html_Element {

    private $_rules = array();
    private $_buttonsDiv = null;

    private $_validationResult = null;

    private $_signature = null;
    private $_securityToken = null;

    private $_submittedValues = null;

    private $errorList = null;

    private $sectionStack = array();
    private $currentSection = null;

    public function __construct($id, $attributes = null) {

        $this->currentSection = $this;

        if (is_string($attributes)) {
            $attributes = array('method' => $attributes);
        }

        $attributes = $attributes ? $attributes : array();
        $attributes['id'] = $id;

        if (empty($attributes['method'])) {
            $attributes['method'] = 'post';
        }

        if (!isset($attributes['novalidate'])) {
            $attributes['novalidate'] = true;
        }

        parent::__construct('form', $attributes);
    }

    /**
     * Adds a field or element to this form.
     */
    public function &add($typeOrElement, $name = null, $label = null, $attributes = null) {

        $field = null;
        $wrapper = null;

        if ($typeOrElement instanceof Octopus_Html_Element) {
            $field = $typeOrElement;
            $this->currentSection->append($field);
        } else {
            $field = Octopus_Html_Form_Field::create($typeOrElement, $name, $label, $attributes);
            if ($field) {
                $wrapper = $this->wrapField($field);
                $this->currentSection->append($wrapper);
            }
        }

        if ($field->type == 'file') {
            $this->setAttribute('enctype', 'multipart/form-data');
        }

        return $field;
    }

    /**
     * Adds a button to this form. Buttons added with this method (as opposed
     * to add()) will be gathered together in a single 'buttons' <div>.
     */
    public function addButton($type, $name = null, $value = null, $text = null, $attributes = null) {

        // Fix for multiple <button> elements in IE6: http://www.kopz.org/public/documents/css/multiple_buttons_ie_workaround.html

        $button = $this->createButton($type, $name, $value, $text, $attributes);

        if (!$button) {
            return false;
        }

        if (!$this->_buttonsDiv) {
            $this->_buttonsDiv = new Octopus_Html_Element('div', array('class' => 'buttons'));
            $this->append($this->_buttonsDiv);
        }

        $this->_buttonsDiv->append($button);
        return $button;
    }

    /**
     * @return String A hash used to verify that:
     *	1) This form was the one that was submitted
     *	2) (Optionally) The user submitting the form is the user the form
     *     was originally served to.
     */
    public function getSignature() {

    	if (!$this->_signature) {

	    	$id = $this->id;
	    	$method = strtolower($this->getAttribute('method', 'get'));

	    	// Not secure, but used by wasSubmitted() to check if this
	    	// form was actually submitted
			$this->_signature = md5($id . '|' . $method);

    	}

    	$result = $this->_signature;
    	if ($this->_securityToken) {
    		$result .= '|' . $this->_securityToken;
    	}

    	return $result;
    }

    /**
     * @return Mixed If a form submission has been made, the contents of
     * the __octform field, or false if there is no available signature.
     */
    protected function getSubmittedSignature() {

    	if (is_array($this->_submittedValues) && isset($this->_submittedValues['__octform'])) {
    		return $this->_submittedValues['__octform'];
    	}

    	$values = $this->getSubmittedValues();

    	if (isset($values['__octform'])) {
    		return $values['__octform'];
    	}

    	return false;

    }

    /**
     * @return Array Values posted to this form. getValues() takes this array
     * and filters it to only those values that have corresponding form
     * fields.
     */
    private function getSubmittedValues() {

    	$requestMethod = isset($_SERVER['REQUEST_METHOD']) ? strtolower($_SERVER['REQUEST_METHOD']) : 'get';
    	$formMethod = strtolower($this->getAttribute('method', 'get'));

    	if ($requestMethod !== $formMethod) {
    		return array();
    	}

        switch($requestMethod) {

            case 'get':
                return $_GET;

            default:
                return $_POST;
        }

    }

    /**
	 * Modifies the signature of this form such that it is only valid for
	 * the given user.
	 * @param $user Mixed One of:
	 *		- Number, e.g. a user ID
	 *		- Octopus_Model, in which case the ID will be used.
     */
    public function secure($user, $action = null) {

    	$id = null;

    	if ($user instanceof Octopus_Model) {
    		$id = $user->id;
    		if (!$id) {
    			throw new Octopus_Exception("Can't secure a form using an unsaved model.");
    		}
    	} else if (is_object($user) || is_array($user)) {
    		throw new Octopus_Exception("Invalid argument passed to " . __METHOD__);
    	} else {
    		$id = $user;
    	}

    	if ($action === null) {

    		// Compose action from form attributes
    		$id = $this->id;
    		$method = strtolower($this->getAttribute('method', 'get'));
    		$action = $this->action;

    		$action = "$id|$method|$action";
    	}

    	$this->_securityToken = get_security_token($id, $action);

        return $this;
    }

    /**
     * @return Boolean Whether this form has been secured by calling secure()
     */
    public function isSecured() {
        return !!$this->_securityToken;
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
     * Starts a new section in this form.
     * @return Octopus_Html_Element The section <div>.
     */
    public function beginSection($id, $label = null, $attributes = null) {

        if ($attributes === null) {
            if (is_array($label)) {
                $attributes = $label;
                $label = null;
            } else {
                $attributes = array();
            }
        }

        $attributes['id'] = $id;

        $tag = 'div';
        if (isset($attributes['tag'])) {
            $tag = $attributes['tag'];
            unset($attributes['tag']);
        }

        $section = new Octopus_Html_Element($tag, $attributes);
        $section->addClass('formSection');

        $title = null;
        $headingTag = 'h' . (count($this->sectionStack) + 2);

        if ($label === null) {
            $label = humanize($id);
        } else {

            $label = trim($label);

            if ($label && preg_match('/^h\d+$/i', $label, $m)) {
                $headingTag = $m[1];
                $label = humanize($id);
            } else if (preg_match('#<h(\d+)[^>]*>.*</h\1>#i', $label)) {
                // We got actual HTML for the title
                $title = $label;
            }

        }

        if ($label) {

            if (!$title) {
                $title = new Octopus_Html_Element($headingTag, array('class' => 'formSectionTitle'));
                $title->html($label);
            }

            $section->append($title);
        }

        $this->currentSection = $section;
        $this->sectionStack[] = $section;

        $this->append($section);

        return $section;
    }

    /**
     * Ends the last section started with beginSection.
     */
    public function endSection() {

        array_pop($this->sectionStack);

        $ct = count($this->sectionStack);
        if ($ct) {
            $this->currentSection = $this->sectionStack[$ct - 1];
        } else {
            $this->currentSection = $this;
        }

        return $this;
    }

    /**
     * @return Array All fields in this form.
     */
    public function &getFields() {
        $result = array();
        self::getFieldsRecursive($this, $result);
        return $result;
    }

    /**
     * Finds in this form by name.
     */
    public function getField($name) {
        $result = array();
        self::getFieldsRecursive($this, $result, $name);
        return array_shift($result);
    }

    private static function getFieldsRecursive($el, &$array, $name = null) {

        if (!$el || !($el instanceof Octopus_Html_Element)) {
            return;
        }

        if ($el instanceof Octopus_Html_Form_Field) {

            if ($name === null) {
                $array[$el->name] = $el;
            } else if ($el->name === $name) {
                $array[$el->name] = $el;
                return true;
            }
        }

        foreach($el->children() as $child) {
            $found = self::getFieldsRecursive($child, $array, $name);
            if ($found) return $found;
        }

    }

    /**
     * @return Mixed the value for the given field.
     */
    public function getValue($field, $default = null) {

        $values = $this->getValues();

        if (isset($values[$field])) {
            return $values[$field];
        } else {
            return $default;
        }

    }

    /**
     * @return Array The set of values posted for this form.
     */
    public function getValues() {

    	$this->loadSubmittedValues();

        $values = array();

        foreach($this->children() as $child) {
            self::getValuesRecursive($child, $values);
        }

        return $values;
    }

    /**
     * Scans through the form and fills an array with the contents of
     * the form.
     */
    private static function getValuesRecursive($el, &$values) {

        if (!$el || !($el instanceof Octopus_Html_Element)) {
            return;
        }

        if ($el instanceof Octopus_Html_Form_Field) {
            $el->readValue($values);
        }

        foreach($el->children() as $child) {
            self::getValuesRecursive($child, $values);
        }

    }

    /**
     * Sets the value of a single field in this form.
     */
    public function setValue($fieldName, $value) {

    	$this->loadSubmittedValues();

        $field = $this->getField($fieldName);

        if (!$field) {
        	throw new Octopus_Exception("Field not found: $fieldName");
        }

        $field->val($value);

        return $this;
    }

    /**
     * Sets the data in this form.
     */
    public function setValues($values) {

    	// Don't re-initialize this form from submitted values
    	$this->_submittedValues = array();

        if (class_exists('Octopus_Model') && $values instanceof Octopus_Model) {
            $values = $values->toArray();
        } else if (is_object($values)) {
            $values = get_object_vars($values);
        }

        $this->_validationResult = null;

        foreach($this->children() as $child) {
            $this->setValuesRecursive($child, $values);
        }

        return $this;
    }

    /**
     * Simulates a valid form submission. After you call this, wasSubmitted()
     * will return true and $values will be used in validation.
     */
    public function submit(Array $values) {

    	$values['__octform'] = $this->getSignature();

    	$this->setValues($values);
    	$this->_submittedValues = $values;

        return $this;
    }

    private function setValuesRecursive($el, &$values) {

        if (!$el || !($el instanceof Octopus_Html_Element)) {
            return;
        }

        if ($el instanceof Octopus_Html_Form_Field) {
        	$el->loadValue($values);
        }

        foreach($el->children() as $child) {
            $this->setValuesRecursive($child, $values);
        }

    }

    private function loadSubmittedValues() {

    	if ($this->_submittedValues !== null) {
    		return;
    	}

    	if ($this->_submittedValues === null) {
    		// Submitted values haven't been loaded into the form yet, so
    		// do that before retrieving them.
    		$this->_submittedValues = $this->getSubmittedValues();
    		$this->setValues($this->_submittedValues);
    	}

    }

    /**
     * Adds a callback rule to this form.
     */
    public function mustPass($callback, $message = null) {
        return $this->addRule(new Octopus_Html_Form_Rule_Callback($callback, $message));
    }

    /**
     * Clears out the form.
     */
    public function reset() {
        $this->setValues(array());
        $this->_validationResult = null;
        return $this;
    }

    /**
     * Generates an array containing all data necessary to render this form in
     * a template.
     */
    public function toArray() {

        $result = array();
        self::attributesToArray($this->getAttributes(), $result);

        $result['open_tag'] = '<form ' . $result['attributes'] . ">\n" . $this->getSignatureFieldHtml();

        // TODO: Do all this an a 'real' meta field
        if ($this->_hiddenSubmissionInput) {
            $result['open_tag'] .= $this->_hiddenSubmissionInput->render(true);
        }

        $result['close_tag'] = '</form>';

        $result['fields'] = array();

        if ($this->_validationResult) {
            $result['valid'] = $this->_validationResult->success;
            $result['errors'] = $this->_validationResult->errors;
        } else {
            $result['valid'] = true;
            $result['errors'] = array();
        }

        foreach($this->children() as $child) {
            $this->toArrayRecursive($child, $result);
        }

        if ($this->_buttonsDiv) {

            $result['buttons'] = array();

            foreach($this->_buttonsDiv->children() as $button) {
                if ($button instanceof Octopus_Html_Element) {
                    $result['buttons'][$button->id] = trim($button->render(true));
                }
            }

            $result['buttons']['html'] = trim($this->_buttonsDiv->render(true));

        }

        return $result;

    }

    private function toArrayRecursive($el, &$result, $ignore = array()) {

        if (!$el || !($el instanceof Octopus_Html_Element)) {
            return;
        }

        if ($el instanceof Octopus_Html_Form_Field) {

            $name = $el->name;

            if (empty($ignore[$name])) {

                $ar = $el->toArray();

                if (!isset($result[$name])) {
                    $result[$name] = $ar;
                }

                $result['fields'][$name] = $ar;
            }
        }

        foreach($el->children() as $child) {
            $this->toArrayRecursive($child, $result);
        }
    }

    /**
     * Validates data in this form. Use <b>setValues</b> to set the values
     * to be validated.
     * @param $result Object Will be set to an object w/ details about the
     * validation result, with the following keys:
     *
     *  <ul>
     *      <li>success</li>
     *      <li>errors</li>
     *  </ul>
     *
     * @return bool True on success, false otherwise.
     */
    public function validate(&$result = null) {

        $values = $this->getValues();

        $result = new StdClass();
        $result->errors = array();

        if ($this->validateSecurityToken()) {

	        foreach($this->children() as $c) {
	            $this->validateRecursive($c, $values, $result);
	        }

	        foreach($this->_rules as $r) {

	            $ruleResult = $r->validate($this, $values);

	            if ($ruleResult === true) {
	                continue;
	            } else if ($ruleResult === false) {
	                $result->errors[] = $r->getMessage($this, $values);
	            } else if (is_string($ruleResult)) {
	                $result->errors[] = $ruleResult;
	            } else if (is_array($ruleResult)) {
	                foreach($ruleResult as $err) {
	                    $result->errors[] = $err;
	                }
	            }

	        }
		} else {
			$result->errors[] = 'This form has expired.';
		}

        $result->success = (count($result->errors) == 0);

        $this->_validationResult = $result;

        $this->updateErrorList($result);

        return $result->success;
    }

    protected function updateErrorList($validationResult) {

        if ($validationResult->success) {

            if ($this->errorList !== null) {
                $this->remove($this->errorList);
                $this->errorList = null;
            }

            return;
        }

        if (!$this->errorList) {
            $this->errorList = new Octopus_Html_Element('ul', array('class' => 'formErrors'));
            $this->prepend($this->errorList);
        } else {
            $this->errorList->clear();
        }

        foreach($validationResult->errors as $err) {
            $li = new Octopus_Html_Element('li');
            $li->html($err);
            $this->errorList->append($li);
        }


    }

    /**
     * Creates a new <button> element.
     */
    protected function createButton($type, $name = null, $value = null, $text = null, $attributes = null) {

        $attributes = $attributes ? $attributes : array();

        if (is_array($type)) {
            $attributes = array_merge($type, $attributes);
            $type = isset($attributes['type']) ? $attributes['type'] : 'button';
        }

        if (is_array($name)) {
            $attributes = array_merge($name, $attributes);
            $name = null;
        }

        if (is_array($value)) {
            $attributes = array_merge($value, $attributes);
            $value = null;
        }

        if (is_array($text)) {
            $attributes = array_merge($text, $attributes);
            $text = null;
        }


        if (is_string($name)) {

            if ($value === null && $text === null) {

                // createButton($type, $text)
                $text = $name;
                $name = null;

            }

        }


        if ($text === null && isset($attributes['label'])) {
            $text = $attributes['label'];
        }

        if ($value === null && isset($attributes['value'])) {
            $value = $attributes['value'];
        }

        if ($name === null && isset($attributes['name'])) {
            $name = $attributes['name'];
        }

        unset($attributes['type']);
        unset($attributes['name']);
        unset($attributes['value']);
        unset($attributes['label']);

        // Support <input type="image" />
        if (preg_match('/\.(png|gif|jpe?g)$/i', $type)) {
            $attributes['src'] = $type;
            $type = 'image';

            if ($text && !isset($attributes['alt'])) {
                $attributes['alt'] = $text;
            }

        }

        $type = $type ? strtolower($type) : $type;

        switch($type) {

            case 'button':
            case 'submit':
            case 'reset':

                $attributes['type'] = $type;
                if ($name !== null) $attributes['name'] = $name;
                if ($value !== null) $attributes['value'] = $value;

                $button = new Octopus_Html_Element('button', $attributes);
                $button->addClass($type, 'button');
                if ($text !== null) $button->html($text);

                return $button;

            case 'a':
            case 'link':
            case 'submit-link':
            case 'reset-link':

                if (!isset($attributes['href'])) $attributes['href'] = '#';
                $attributes['href'] = u($attributes['href']);

                $link = new Octopus_Html_Element('a', $attributes);
                $link->addClass(preg_replace('/-?link/', '', $type));

                if ($text !== null) $link->html($text);

                return $link;

            default:

                $attributes['type'] = $type;
                if ($name !== null) $attributes['name'] = $name;
                if ($value !== null) $attributes['value'] = $value;

                $input = new Octopus_Html_Element('input', $attributes);
                $input->addClass($type, 'button');

                return $input;

        }
    }

    public function &renderContent() {
    	// Include the signature field in the rendered content.
		$result = $this->getSignatureFieldHtml() . "\n" . parent::renderContent();
		return $result;
    }

    /**
     * For fields added like add('type', 'name', array()), wraps in a div and
     * adds a label.
     */
    protected function wrapField($field) {

        if ($field->type == 'hidden') {
            return $field;
        }

        $label = new Octopus_Html_Element('label');
        $field->addLabel($label);

        $wrapper = new Octopus_Html_Element('div');
        $wrapper->id = $field->wrapperId;
        $wrapper->addClass('field', $field->wrapperClass);

        if ($field->type == 'checkbox') {

            // HACK: Checkboxes are usually like [x] Label rather than Label [x]


            $wrapper->append($field);
            $wrapper->append($label);
        } else {
            $wrapper->append($label);
            $wrapper->append($field);
        }

        $field->wrapper = $wrapper;

        return $wrapper;
    }

    private function validateRecursive(&$el, &$values, &$result) {

        if ($el instanceof Octopus_Html_Element) {

            if ($el instanceof Octopus_Html_Form_Field) {

                $fieldResult = $el->validate($values);
                $name = $el->name;

                foreach($fieldResult->errors as $err) {

                    if (!isset($result->errors[$name])) {
                    	$result->errors[$name] = array();
                    }

                    $result->errors[$name][] = $err;
                }

            }

            foreach($el->children() as $c) {
                $this->validateRecursive($c, $values, $result);
            }

        }
    }

    /**
     * Copies the given set of attributes into the given array, and builds
     * a key called 'attributes' in $ar that contains the full attribute html
     * string.
     */
    public static function attributesToArray($attributes, &$ar) {

        $ar['attributes'] = '';

        foreach($attributes as $attr => $value) {

            $safeAttr = htmlspecialchars($attr);
            $safeValue = htmlspecialchars($value);

            $rendered = Octopus_Html_Element::renderAttribute($safeAttr, $safeValue, true);

            if ($rendered) {

                $ar['attributes'] .= ($ar['attributes'] ? ' ' : '') . $rendered;

                if (strpos($rendered, '=') == false) {
                    // Attribute has no value
                    $ar[$attr] = $safeAttr;
                } else {
                    $ar[$attr] = $safeValue;
                }

            }
        }

    }


    /**
     * @return Bool Whether the form has been submitted.
     */
    public function wasSubmitted() {

    	if ($this->_submittedValues) {
    		return true;
    	}

    	// The form signature has 2 parts: The first is an unsecure hash
    	// of the id/method/action, the second is the actual security
    	// token used by secure(). To check for submission, we only
    	// need to look at the first part. The validate() function
    	// handles validating the secure() call.

    	$submittedSig = $this->getSubmittedSignature();
    	if (!$submittedSig) {
    		return false;
    	}
    	$submittedSig = explode('|', $submittedSig);
    	$submittedSig = array_shift($submittedSig);
    	if (!$submittedSig) return false;

    	$sig = explode('|', $this->getSignature());
    	$sig = array_shift($sig);

    	return $sig === $submittedSig;
    }

    public function submitted() {
        return $this->wasSubmitted();
    }

    /**
     * @return Bool True if the form is unsecured or was submitted with a valid
     * security token, false otherwise.
     */
    protected function validateSecurityToken() {

    	if (!$this->_securityToken) {
    		return true;
    	}

    	$sig = $this->getSubmittedSignature();
    	if (!$sig) {
    		return false;
    	}

    	$sig = explode('|', $sig);
    	$submittedToken = array_pop($sig);

    	return $submittedToken === $this->_securityToken;
    }

    private function getSignatureFieldHtml() {
    	return <<<END
<input type="hidden" name="__octform" value="{$this->getSignature()}" />
END;
    }

}

?>
