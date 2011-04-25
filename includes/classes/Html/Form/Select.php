<?php

SG::loadClass('SG_Html_Element');

class SG_Html_Form_Select extends SG_Html_Element {

    private $_valueFields = array('value', 'id', '/.*_id$/i');
    private $_textFields = array('name', 'title', 'desc', 'summary', 'description');

    public function __construct($name, $attributes = null) {
        parent::__construct('select', $attributes);
        $this->setAttribute('name', $name);
    }

    /**
     * Adds a single option to this select.
     * @return Object An SG_Html_Element for the option added.
     */
    public function addOption($value, $text = null, $attributes = null) {

        $opt = $this->createOption($value, $text, $attributes);
        $this->append($opt);
        return $opt;

    }

    /**
     * Adds multiple options to the select.
     * @return Object $this for method chaining.
     */
    public function addOptions($options) {

        if (empty($options)) {
            return $this;
        }

        if (is_string($options) || (is_array($options) && count($options) == 2 && is_callable($options))) {

            $options = call_user_func($options, $this);
            $this->addOptions($options);
            return $this;

        }

        $attributes = null;

        foreach($options as $value => $text) {

            if ((is_object($text) || is_array($text)) || is_numeric($value)) {
                $value = $text;
                $text = null;
            }

            $opt = $this->createOption($value, $text, null);
            $this->append($opt);
        }

        return $this;
    }

    /**
     * Factory method for creating <options>
     */
    protected function &createOption($value, $text, $attributes) {

        if (is_array($text) && $attributes === null) {
            $attributes = $text;
            $text = null;
        }

        if (is_object($value) && $text === null) {
            $this->getValueAndTextFromObject($value, $value, $text);
        } else if (is_array($value) && $text === null) {
            $this->getValueAndTextFromArray($value, $value, $text);
        }

        if ($value !== null && $text === null) {
            $text = $value;
        } else if ($text !== null && $value === null) {
            $value = $text;
        }

        $attributes = $attributes ? $attributes : array();
        $attributes['value'] = $value;

        $opt = new SG_Html_Element('option', $attributes);
        $opt->text($text);

        return $opt;
    }

    /**
     * Scans $obj for any properties whose names are present in the $fieldNames
     * array and returns the best field match.
     */
    private static function findCandidateField(&$obj, &$fieldNames) {

        $objectVars = null;

        foreach($fieldNames as $fieldName) {

            if (strncmp($fieldName, '/', 1) == 0) {

                // The fieldname is actually a pattern, see if any
                // properties match
                if ($objectVars === null) {

                    if (is_object($obj)) {
                        $objectVars = get_object_vars($obj);
                    } else if (is_array($obj)) {
                        $objectVars = $obj;
                    }
                }

                foreach($objectVars as $key => $value) {
                    if (preg_match($fieldName, $key)) {
                        return $key;
                    }
                }

            } else if (isset($obj->$fieldName)) {
                    return $fieldName;
            }

        }

        return false;

    }

    private function getValueAndTextFromArray($array, &$value, &$text) {

        $valueField = isset($this->valueField) ? $this->valueField : null;
        $textField = isset($this->textField) ? $this->textField : null;

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

    private function getValueAndTextFromObject($obj, &$value, &$text) {

        $valueField = isset($this->valueField) ? $this->valueField : null;
        $textField = isset($this->textField) ? $this->textField : null;

        $value = $text = null;

        if ($valueField && isset($obj->$valueField)) {
            $value = $obj->$valueField;
        } else {

            $valueField = self::findCandidateField($obj, $this->_valueFields);
            if ($valueField) {
                $value = $obj->$valueField;
            }

        }

        if ($textField && isset($obj->$textField)) {
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
