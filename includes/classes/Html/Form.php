<?php

Octopus::loadClass('Octopus_Html_Form_Field');

class Octopus_Html_Form extends Octopus_Html_Element {

    private $_rules = array();
    private $_buttonsDiv = null;

    private $_values = null;
    private $_validationResult = null;
    private $_security_user_id = null;
    private $_security_action = null;
    private $_security_field = '__security_token';


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

        if (empty($attributes['method'])) {
            $attributes['method'] = 'post';
        }

        parent::__construct('form', $attributes);
    }

    public function &add($typeOrElement, $name = null, $label = null, $attributes = null) {

        $field = null;
        $wrapper = null;

        if ($typeOrElement instanceof Octopus_Html_Element) {
            $field = $typeOrElement;
            $this->append($field);
        } else {
            $field = Octopus_Html_Form_Field::create($typeOrElement, $name, $label, $attributes);
            if ($field) {
                $wrapper = $this->wrapField($field);
                if ($wrapper) $this->append($wrapper);
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

    public function secure($user_id, $action = null) {
        if ($action === null) {
            $action = $this->id;
        }

        $this->_security_user_id = $user_id;
        $this->_security_action = $action;
        $token = get_security_token($user_id, $action);

        $el = new Octopus_Html_Element('input', array('type' => 'hidden'));
        $el->name = $this->_security_field;
        $el->value = $token;

        $this->append($el);
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
     * Finds in this form by name.
     */
    public function getField($name) {
        return self::getFieldRecursive($this, $name);
    }

    private static function getFieldRecursive($el, $name) {

        if (!$el || !($el instanceof Octopus_Html_Element)) {
            return;
        }

        if ($el instanceof Octopus_Html_Form_Field) {
            if ($el->name == $name) {
                return $el;
            }
        }

        foreach($el->children() as $child) {
            $found = self::getFieldRecursive($child, $name);
            if ($found) return $found;
        }

    }

    /**
     * @return Mixed the value for the given field.
     */
    public function getValue($field, $default = null) {

        $values =& $this->getValues();

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

        if (!empty($this->_values)) {
            return $this->_values;
        }

        $method = strtolower($this->getAttribute('method', 'get'));

        switch($method) {

            case 'get':
                $sourceArray =& $_GET;
                break;

            default:
                $sourceArray =& $_POST;
                break;
        }

        $this->_values = array();

        foreach($this->children() as $child) {
            self::getValuesRecursive($child, $sourceArray, $this->_values);
        }

        if ($this->_security_action !== null) {
            $this->_values[$this->_security_field] = isset($sourceArray[$this->_security_field]) ? $sourceArray[$this->_security_field] : '';
        }

        return $this->_values;
    }

    /**
     * Scans through the form and fills an array with actual data meant for
     * the form.
     */
    private static function getValuesRecursive($el, &$sourceArray, &$values) {

        if (!$el || !($el instanceof Octopus_Html_Element)) {
            return;
        }

        if ($el instanceof Octopus_Html_Form_Field) {
            $el->readValue($sourceArray, $values);
        }

        foreach($el->children() as $child) {
            self::getValuesRecursive($child, $sourceArray, $values);
        }

    }

    /**
     * Sets the data in this form.
     */
    public function setValues($values) {

        if (class_exists('Octopus_Model') && $values instanceof Octopus_Model) {
            $values = $values->toArray();
        } else if (is_object($values)) {
            $values = get_object_vars($values);
        }

        $this->_values = $values;
        $this->_validationResult = null;

        foreach($this->children() as $child) {
            $this->setValuesRecursive($child, $values);
        }

        return $this;
    }

    private function setValuesRecursive($el, &$values) {

        if (!$el || !($el instanceof Octopus_Html_Element)) {
            return;
        }

        if ($el instanceof Octopus_Html_Form_Field) {

            $name = preg_replace('/\[\]$/', '', $el->name);

            if (isset($values[$name])) {
                $el->val($values[$name]);
            } else {
                $el->val(null);
            }

        }

        foreach($el->children() as $child) {
            $this->setValuesRecursive($child, $values);
        }

    }

    /**
     * Adds a callback rule to this form.
     */
    public function mustPass($callback, $message = null) {
        Octopus::loadClass('Octopus_Html_Form_Rule_Callback');
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

        $result = array('form' => array('open_tag' => '', 'close_tag' => ''));
        self::attributesToArray($this->getAttributes(), $result['form']);

        $result['form']['open_tag'] = '<form ' . $result['form']['attributes'] . '>';
        $result['form']['close_tag'] = '</form>';

        $result['form']['fields'] = array();

        if ($this->_validationResult) {
            $result['form']['valid'] = $this->_validationResult->success;
            $result['form']['errors'] = $this->_validationResult->errors;
        } else {
            $result['form']['valid'] = true;
            $result['form']['errors'] = array();
        }

        foreach($this->children() as $child) {
            $this->toArrayRecursive($child, $result);
        }

        return $result;

    }

    private function toArrayRecursive($el, &$result) {

        if (!$el || !($el instanceof Octopus_Html_Element)) {
            return;
        }

        if ($el instanceof Octopus_Html_Form_Field) {
            $ar = $el->toArray();
            $result[$el->name] = $ar;
            $result['form']['fields'][$el->name] = $ar;
        }

        foreach($el->children() as $child) {
            $this->toArrayRecursive($child, $result);
        }
    }

    /**
     * Validates data in this form.
     * @param $values Array Data to validate. If not specified, then either
     * $_GET or $_POST will be used as appropriate.
     * @return Object An object with two properties: success and errors.
     */
    public function validate($values = null) {

        if ($values === null) {
            $values = $this->getValues();
        } else {
            $this->setValues($values);
        }

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

        if (!$this->verifySecurityToken()) {
            $result->errors[] = 'This form has expired';
        }

        $result->success = (count($result->errors) == 0);
        $result->hasErrors = !$result->success;

        $this->_validationResult = $result;

        return $result;

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

            case 'link':
            case 'submit-link':
            case 'reset-link':

                $attributes['href'] = '#';
                $link = new Octopus_Html_Element('a', $attributes);
                $link->addClass(preg_replace('/-?link/', '', $type), 'button');

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

    /**
     * For fields added like add('type', 'name', array()), wraps in a div and
     * adds a label.
     */
    protected function wrapField($field) {

        $label = new Octopus_Html_Element('label');
        $field->addLabel($label);

        $wrapper = new Octopus_Html_Element('div');
        $wrapper->id = $field->wrapperId;
        $wrapper->addClass('field', $field->wrapperClass);

        $wrapper->append($label);
        $wrapper->append($field);

        $field->wrapper = $wrapper;

        return $wrapper;
    }

    private function validateRecursive(&$el, &$values, &$result) {

        if ($el instanceof Octopus_Html_Element) {

            if ($el instanceof Octopus_Html_Form_Field) {

                $fieldResult = $el->validate($values);
                $result->errors += $fieldResult->errors;

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

    private function verifySecurityToken() {

        if ($this->_security_user_id !== null) {
            $values = $this->getValues();

            $token = $values[ $this->_security_field ];
            if (!verify_security_token($token, $this->_security_user_id, $this->_security_action)) {
                return false;
            }
        }

        return true;

    }

    /**
     * @return Bool Whether the form has been submitted.
     */
    public function wasSubmitted() {

        $actualMethod = strtolower(isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'get');
        $thisMethod = strtolower($this->getAttribute('method', 'get'));

        if ($actualMethod != $thisMethod) {
            return false;
        }

        $this->getValues();
        return !empty($this->_values);
    }

    public function submitted() {
        return $this->wasSubmitted();
    }

}

?>
