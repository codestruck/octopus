<?php

/**
 * Filter that displays a list of options.
 */
class Octopus_Html_Table_Filter_Select extends Octopus_Html_Table_Filter {

    private $emptyOption;

    public function __construct(Octopus_Html_Table $table, $type, $id, $label, $options) {

        if ($options instanceof Octopus_Model_ResultSet) {
            $options = array('options' => $options);
        } else if ($label instanceof Octopus_Model_ResultSet) {
            if (!$options) $options = array();
            $options['options'] = $label;
            $label = null;
        }

        parent::__construct($table, $type, $id, $label, $options);
    }

    /**
     * @return Octopus_Html_Element The valueless option selected by default.
     */
    public function getDefaultOption() {
        $this->getElement(); // force <select> creation
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

    protected function createElement() {

        $attribs = isset($this->options['attributes']) ? $this->options['attributes'] : null;

        $el = Octopus_Html_Form_Field::create('select', $this->id, $attribs);
        $el->name = $this->id;

        if (isset($this->options['options'])) {
            $el->addOptions($this->options['options']);
        }

        $this->emptyOption = new Octopus_Html_Element('option', array('value' => ''), 'Choose One');
        $el->prepend($this->emptyOption);

        return $el;
    }


    protected function initializeOptions($options) {

        if (is_array($options) && !isset($options['options']) && !isset($options['function'])) {
            // Allow passing just the options in
            $htmlOptions = $options;
            $options = array('options' => $htmlOptions);
        }

        $options = parent::initializeOptions($options);

        return $options;
    }

}

