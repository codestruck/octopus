<?php

class Octopus_Html_Form_Field_Select extends Octopus_Html_Form_Field {

    private $_valueFields = array('value', 'id', '/.*_id$/i');
    private $_textFields = array('name', 'title', 'desc', 'summary', 'description', 'text');

    protected $valueField = null;
    protected $textField = null;

    public function __construct($type, $name, $label, $attributes = null, $tag = 'select') {

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

        parent::__construct($tag, $type, $name, $label, $attributes);
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

    	$this->resolveValueAndTextForOption($value, $text, $attributes);
        $opt = $this->createOption($value, $text, $attributes);
        if ($opt) $this->appendOption($opt);

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

		    	$this->resolveValueAndTextForOption($value, $text, $attributes);
                $opt = $this->createOption($value, $text, $attributes);
                if ($opt) $this->appendOption($opt);
            }
        }

        return $this;
    }

    /**
     * @return Array An array of Octopus_Html_Elements, keyed on option value.
     */
    public function getOptions() {

    	$opts = array();
    	foreach($this->children() as $opt) {

    		if ($opt instanceof Octopus_Html_Element) {

				if ($opt->is('option')) {
					$value = ($opt->value === null ? $opt->text() : $opt->value);
					$opts[$value] = $opt;
				} else if ($opt->is('optgroup')) {
					foreach($opt->children() as $subopt) {
						if ($subopt->is('option')) {
							$value = ($subopt->value === null ? $subopt->text() : $subopt->value);
							$opts[] = $subopt;
						}
					}
				}

    		}

    	}

        return $opts;
    }

    public function getAttribute($attr, $default = null) {

        if ($attr === 'value') {
            return $this->getSelectedValue();
        }

        return parent::getAttribute($attr, $default);

    }

    public function setAttribute($attr, $value) {

        if ($attr === 'value') {
            return $this->setSelectedValue($value);
    	}

        return parent::setAttribute($attr, $value);

    }

    public function &toArray() {

        $result = parent::toArray();
        $result['options'] = array();

        foreach($this->getOptions() as $value => $opt) {
        	$result['options'][$value] = $opt->text();
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

	/**
	 * Appends a single option to this control. This is provided as a
	 * hook for Form_Field_Radio.
	 */
	protected function appendOption(Octopus_Html_Element $option) {
		$this->append($option);
	}

    protected function attributeChanged($attr, $oldValue, $newValue) {

    	// Use array-style name if multiple is specified
    	if ($attr === 'multiple') {
    		$this->multipleAttributeChanged($oldValue, $newValue);
    	} else if ($attr === 'name') {
    		$this->nameAttributeChanged($oldValue, $newValue);
    	}

    }

    protected function multipleAttributeChanged($oldValue, $newValue) {

		if ($newValue) {
			$this->setAttribute('name', end_in('[]', $this->getAttribute('name')));
		} else {
			$this->setAttribute('name', str_replace('[]', '', $this->getAttribute('name')));
		}

    }

    protected function nameAttributeChanged($oldValue, $newValue) {

	    if ($this->isMultipleSelect() && $newValue !== '') {
			$this->setAttribute('name', end_in('[]', $newValue));
		} else {
			$this->setAttribute('name', str_replace('[]', '', $newValue));
		}

    }

    protected function isMultipleSelect() {
    	return $this->getAttribute('multiple');
    }

    /**
     * Examines $value and $text and sets them to the appropriate things
     * based on what they are.
     */
    protected function resolveValueAndTextForOption(&$value, &$text, &$attributes) {

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

    }

    /**
     * Factory method for creating <options>
     */
    protected function createOption($value, $text, $attributes) {

        if ($attributes === null) $attributes = array();
        $attributes['value'] = $value;

        $opt = new Octopus_Html_Element('option', $attributes);
        $opt->html(strip_tags($text));

        return $opt;
    }

    /**
     * Called to read the state of selected items in the list.
     * @return Mixed The selected value or values.
     */
    protected function getSelectedValue() {

    	$multiple = $this->isMultipleSelect();
    	$result = $multiple ? array() : null;
    	$firstValue = null;

        foreach($this->getOptions() as $optionVal => $o) {

            if ($o->selected) {

            	if ($multiple) {
            		$result[] = $optionVal;
            	} else {
            		return $optionVal;
            	}

            } else if (!$multiple && $firstValue === null) {

                // by default, 1st option is selected
            	$firstValue = $optionVal;

            }
        }

        return $result === null ? $firstValue : $result;
    }

    /**
     * Called to modify the state of selected items in this list.
     * @param $newValue mixed The value / values to select.
     */
    protected function setSelectedValue($newValue) {

        $values = is_array($newValue) ? $newValue : array($newValue);
        $multiple = $this->isMultipleSelect();

        if (!$multiple && count($values) > 1) {
        	$values = array_slice($value, 0, 1);
        }

        $changed = false;
		$somethingSelected = false;
		$options = $this->getOptions();


        foreach($options as $optionVal => $o) {

            $o->selected = false;

            if ($multiple || !$somethingSelected) {

		        foreach($values as $value) {

			        if ($value instanceof Octopus_Model) {
			            $value = $value->id;
			        }

		            if ($optionVal == $value) {

		            	if (!$o->selected) {
		            		$changed = true;
		            	}

		            	$o->selected = true;
		            	$somethingSelected = true;
		            }

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
