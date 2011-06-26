<?php

/**
 * Filter that displays a list of options.
 */
class Octopus_Html_Table_Filter_Select extends Octopus_Html_Table_Filter {

    private $emptyOption;

    public function __construct($type, $id, $label, $options) {

        if ($options instanceof Octopus_Model_ResultSet) {
            $options = array('options' => $options);
        } else if ($label instanceof Octopus_Model_ResultSet) {
            if (!$options) $options = array();
            $options['options'] = $label;
            $label = null;
        }

        parent::__construct($type, $id, $label, $options);
    }

    /**
     * @return Octopus_Html_Element The valueless option selected by default.
     */
    public function getDefaultOption() {
        return $this->emptyOption;
    }

    /**
     * Gets/sets the default text to display for this select.
     */
    public function defaultText() {

        switch(func_num_args()) {

            case 0:
                return $this->getDefaultOption()->html();

            default:
                $arg = func_get_arg(0);

                if (is_string($arg)) {
                    $this->getDefaultOption()->text($arg);
                }
        }

    }

    protected function applyToResultSet($resultSet) {
        $val = $this->val();
        if ($val || $val === 0) {
            return $resultSet->where(array($this->id => $val));
        } else {
            return $resultSet;
        }
    }

    protected function createElement() {

        $attribs = isset($this->options['attributes']) ? $this->options['attributes'] : null;

        $el = Octopus_Html_Form_Field::create('select', $this->id, $attribs);
        $el->name = $this->id;

        $this->emptyOption = new Octopus_Html_Element('option', array('value' => ''), 'Choose One');
        $el->prepend($this->emptyOption);

        return $el;
    }


    protected function &initializeOptions(&$options) {

        if (is_array($options) && !isset($options['options'])) {
            // Allow passing just the options in
            $htmlOptions = $options;
            $options = array('options' => $htmlOptions);
        }


        if (isset($options['options'])) {
            if (!isset($options['attributes'])) $options['attributes'] = array();
            $options['attributes']['options'] = $options['options'];
            unset($options['options']);
        }

        $options = parent::initializeOptions($options);

        return $options;
    }

}

?>
