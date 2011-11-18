<?php

class Octopus_Html_Form_Field_Select extends Octopus_Html_Form_Field {

    private $_valueFields = array('value', 'id', '/.*_id$/i');
    private $_textFields = array('name', 'title', 'desc', 'summary', 'description', 'text');

    protected $valueField = null;
    protected $textField = null;

    public function __construct($type, $name, $label, $attributes = null) {

        $options = null;

        if ($attributes instanceof Octopus_Model_ResultSet) {
            $options = $attributes;
            $attributes = array();
        } else if ($label instanceof Octopus_Model_ResultSet) {
            $options = $label;
            $label = null;
        } else if ($attributes && isset($attributes['options'])) {
            $options = $attributes['options'];
            unset($attributes['options']);
        }

        parent::__construct('select', $type, $name, $label, $attributes);
        $this->removeAttribute('type');
        $this->setAttribute('name', $name);

        $this->requireCloseTag = true;

        if ($options) $this->addOptions($options);
    }

    /**
     * Adds a single option to this select.
     * @return Object An Octopus_Html_Element for the option added.
     */
    public function addOption($value, $text = null, $attributes = null) {

        $opt = $this->createOption($value, $text, $attributes);
        $this->append($opt);
        return $opt;

    }

    /**
     * Adds multiple options to the select.
     * @param $options Array An array of options to add.
     * @return Object $this for method chaining.
     */
    public function addOptions($options) {

        $args = func_get_args();

        foreach($args as $options) {

            if (empty($options)) {
                continue;
            }

            if (is_callable($options)) {
                $options = call_user_func($options, $this);
                $this->addOptions($options);
                return $this;
            }

            if (is_string($options)) {
                // A single option
                $this->addOption($options);
                continue;
            }

            $attributes = null;

            foreach($options as $value => $text) {

                if (is_numeric($value)) {

                    if (is_array($text) || is_object($text)) {
                        $value = $text;
                        $text = null;
                    }

                }

                $opt = $this->createOption($value, $text, null);
                $this->append($opt);
            }
        }

        return $this;
    }

    /**
     * @return Array An array where keys are option values and values are
     * the text of options.
     */
    public function &getOptions() {
        $ar = $this->toArray();
        return $ar['options'];
    }

    public function getAttribute($attr, $default = null) {

        if (strcasecmp($attr, 'value') == 0) {
            return $this->getSelectedValue();
        } else {
            return parent::getAttribute($attr, $default);
        }

    }

    public function setAttribute($attr, $value) {

        if ($attr == 'value') {
            return $this->setSelectedValue($value);
        } else if ($attr == 'name') {

        	if ($this->getAttribute('multiple')) {
        		$value = end_in('[]', $value);
        	} else {
        		$value = str_replace('[]', '', $value);
        	}

    	}

        return parent::setAttribute($attr, $value);

    }

    public function &toArray() {

        $result = parent::toArray();
        $result['options'] = array();

        foreach($this->children() as $option) {
            $value = $option->getAttribute('value', null);
            if ($value === null) $value = $option->text();
            $result['options'][$value] = $option->text();
        }

        return $result;
    }

    /**
     * Gets/sets the value of this field. Called without arguments, returns
     * the selected value (or the value of the first option if none is
	 * explicitly selected). Called with arguments, it sets the value.
	 *
	 * If the 'multiple' attribute is true, this will ALWAYS return an array,
	 * even if there is nothing currently selected. When 'multiple' is on, you
	 * can pass an array of values or multiple arguments to select items.
     */
	public function val(/* No args = return val, 1 or more args = set value */) {

		$args = func_get_args();

		switch(count($args)) {

			case 0:
				return $this->getSelectedValue();

			case 1:
				return $this->setSelectedValue($args[0]);

			default:

				return $this->setSelectedValue($args);

		}

	}

    protected function attributeChanged($attr, $oldValue, $newValue) {

    	// Use array-style name if multiple is specified
    	if ($attr == 'multiple') {

    		if ($newValue) {
    			$this->setAttribute('name', end_in('[]', $this->getAttribute('name')));
    		} else {
    			$this->setAttribute('name', str_replace('[]', '', $this->getAttribute('name')));
    		}

    	}
    }

    /**
     * Factory method for creating <options>
     */
    protected function createOption($value, $text, $attributes) {

        if (is_array($text) && $attributes === null) {
            $attributes = $text;
            $text = null;
        }

        if (is_object($value)) {

            if ($value instanceof Octopus_Model) {
                $this->getValueAndTextFromModel($value, $value, $text);
            } else {
                $this->getValueAndTextFromObject($value, $value, $text);
            }

        } else if (is_array($value)) {
            $this->getValueAndTextFromArray($value, $value, $text);
        }

        if ($value !== null && $text === null) {
            $text = $value;
        } else if ($text !== null && $value === null) {
            $value = $text;
        }

        if ($attributes === null) $attributes = array();
        $attributes['value'] = $value;

        $opt = new Octopus_Html_Element('option', $attributes);
        $opt->html(strip_tags($text));

        return $opt;
    }

    private function getSelectedValue() {

    	$result = $this->multiple ? array() : null;
    	$firstValue = null;

        foreach($this->children() as $o) {

            if ($o->selected) {

            	$value = $o->value;
            	if ($value === null) $value = $o->text();

            	if (is_array($result)) {
            		$result[] = $value;
            	} else {
            		return $value;
            	}

            } else if ($result === null && $firstValue === null) {

                // by default, 1st option is selected
            	$firstValue = $o->value;
            	if ($firstValue === null) $firstValue = $o->text();

            }
        }

        return $result === null ? $firstValue : $result;
    }

    private function setSelectedValue($newValue) {

        $values = is_array($newValue) ? $newValue : array($newValue);

        if (!$this->multiple && count($values) > 1) {
        	$values = array_slice($value, 0, 1);
        }

        $changed = false;
		$options = $this->children();

        foreach($options as $o) {

            $optionVal = $o->value;
            if ($optionVal === null) $optionVal = $o->text();

            $o->selected = false;

	        foreach($values as $value) {

		        if ($value instanceof Octopus_Model) {
		            $value = $value->id;
		        }

	            if ($optionVal == $value && !$o->selected) {
	                $o->selected = true;
	                $changed = true;
	            }

        	}

	    }

        if ($changed) $this->valueChanged();

        return $this;
    }

    /**
     * Scans $obj for any properties whose names are present in the $fieldNames
     * array and returns the best field match.
     */
    private static function findCandidateField(&$obj, &$fieldNames) {

        $vars = null;

        $isArray = is_array($obj);
        $isObj = is_object($obj);


        if (!($isArray || $isObj)) {
            return false;
        }

        foreach($fieldNames as $fieldName) {

            if (strncmp($fieldName, '/', 1) == 0) {

                // The fieldname is actually a pattern, see if any
                // properties match

                if ($vars === null) {

                    if ($isArray) {
                        $vars =& $obj;
                    } else if ($isObj) {
                        $vars = get_object_vars($obj);
                    }
                }

                foreach($vars as $key => $value) {

                    if (preg_match($fieldName, $key)) {
                        return $key;
                    }

                }

            } else if ($isObj && isset($obj->$fieldName)) {

                return $fieldName;

            } else if ($isArray && isset($obj[$fieldName])) {
                return $fieldName;
            }

        }

        return false;

    }

    private function getValueAndTextFromArray($array, &$value, &$text) {

        $valueField = $this->valueField;
        $textField = $this->textField;

        $value = $text = null;

        if ($valueField && isset($array[$valueField])) {
            $value = $array[$valueField];
        } else {

            $valueField = self::findCandidateField($array, $this->_valueFields);
            if ($valueField) {
                $value = $array[$valueField];
            }

        }

        if ($textField && isset($array[$textField])) {
            $text = $array[$textField];
        } else {

            $textField = self::findCandidateField($array, $this->_textFields);
            if ($textField) {
                $text = $array[$textField];
            }

        }

        // Save for later
        if ($valueField && !isset($this->valueField)) $this->valueField = $valueField;
        if ($textField && !isset($this->textField)) $this->textField = $textField;

    }

    /**
     * Figures out the value and text to use for an Octopus_Model instance.
     */
    private function getValueAndTextFromModel($obj, &$value, &$text) {
        $value = $obj->id;
        $text = $obj->getDisplayValue();
    }

    private function getValueAndTextFromObject($obj, &$value, &$text) {

        $valueField = isset($this->valueField) ? $this->valueField : null;
        $textField = isset($this->textField) ? $this->textField : null;

        $value = $text = null;

        // TODO: once Octopus_Model supports isset(), remove these hacks

        if ($valueField && (isset($obj->$valueField) || $obj instanceof Octopus_Model)) {
            $value = $obj->$valueField;
        } else {

            $valueField = self::findCandidateField($obj, $this->_valueFields);
            if ($valueField) {
                $value = $obj->$valueField;
            }

        }

        if ($textField && (isset($obj->$textField) || $obj instanceof Octopus_Model)) {
            $text = $obj->$textField;
        } else {

            $textField = self::findCandidateField($obj, $this->_textFields);
            if ($textField) {
                $text = $obj->$textField;
            }

        }

        // Save for later
        if ($valueField && !isset($this->valueField)) $this->valueField = $valueField;
        if ($textField && !isset($this->textField)) $this->textField = $textField;


    }

}

?>
