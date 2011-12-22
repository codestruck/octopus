<?php

/**
 * A control for filtering a table's contents. Any methods called that aren't
 * present on this class get forwarded to an Octopus_Html_Element this class
 * manages. So you can do things like this:
 *
 *	$filter = $table->addFilter('text', 'name');
 *	$filter->addClass('important');
 *
 */
abstract class Octopus_Html_Table_Filter {

    public static $defaults = array(

        /**
         * Attributes to pass to the element this filter uses.
         */
        'attributes' => array(),

        /**
         * Function used to actually filter results. Receives 3 arguments:
         * the Octopus_Html_Table_Filter object, the datasource being filtered,
         * and the Octopus_Html_Table the filter is a part of. Should return
         * the filtered datasource, or null to not filter.
         */
        'function' => null,

    );

    private static $registry = array();

    public $id;

	private $type;
    private $value;
    private $label;
    private $element = null;

    protected $table;
    protected $options;

    public function __construct(Octopus_Html_Table $table, $type, $id, $label, $options) {

    	$this->table = $table;
        $this->type = $type;
        $this->id = $id;
        $this->label = $label === null ? (humanize($id) . ':') : $label;

        $options = $this->initializeOptions($options);
        $this->options = array_merge(self::$defaults, $options);
    }

    public function __call($method, $args) {
    	$element = $this->getElement();
    	return call_user_func_array(array($element, $method), $args);
    }

    /**
     * Executes this filter against the given data source.
     * @param Mixed $dataSource Data source being displayed
     * @param Octopus_Html_Table $table The table being filtered.
     * @return Mixed A filtered data source, or null if it doesn't need to be
     * filtered.
     */
    public function apply(Octopus_DataSource $dataSource) {

    	if (is_callable($this->options['function'])) {
    		return call_user_func($this->options['function'], $this, $dataSource, $this->table);
    	}

    	return $this->defaultApply($dataSource);
    }

    /**
     * Applies this filter to the given data source, assuming no custom function
     * was specified in options.
     */
    protected function defaultApply(Octopus_DataSource $dataSource) {
    	return $dataSource->filter($this->id, $this->val());
    }

    /**
     * Clears the contents of this filter.
     */
    public function clear() {
        return $this->val('');
    }

    /**
     * @return Octopus_Html_Element An element to display for this filter. It will
     * have its value automatically updated by calls to val() or clear().
     */
    public function getElement() {

    	if ($this->element) {
    		return $this->element;
    	}

    	$element = $this->createElement();
    	$this->setElementValue($element, $this->value);

    	return $this->element = $element;
    }

    /**
     * @return String The registered type name for this filter.
     */
    public function getType() {
        return $this->type;
    }

    /**
     * @return Boolean Whether this filter is empty (doesn't have a value and
     * should not be used to filter data in a table).
     */
    public function isEmpty() {

    	$val = $this->val();
    	if ($val === null) return true;

    	return trim($val) === '';
    }

    public function label(/* $label */) {
    	$args = func_get_args();
    	if (count($args)) {
    		return $this->setLabel($args[0]);
    	} else {
    		return $this->getLabel();
    	}
    }

    public function setLabel($label) {
    	$this->label = $label;
    	return $this;
    }

    public function getLabel() {
    	return $this->label;
    }

    /**
     * Creates a label this filter.
     * @return Octopus_Html_Element
     */
    public function createLabelElement() {

    	$text = $this->getLabel();

        if (!$text) {
            return;
        }

        $element = $this->getElement();
        if ($element) {
	        return new Octopus_Html_Element('label', array('class' => 'filterLabel', 'for' => $element->id), $this->label);
	       }
    }

    /**
     * Gets/sets the current value of this filter.
     */
    public function val(/* $val */) {

    	switch(func_num_args()) {
    		case 0:
    			return $this->getValue();
    		default:
    			return $this->setValue(func_get_arg(0));
    	}

    }

    public function setValue($value) {

    	if (is_object($value)) print_backtrace();

    	$this->value = $value;

    	if ($this->element) {
    		$this->setElementValue($this->element, $value);
    	}

    	return $this;
    }

    public function getValue() {

    	if ($this->element) {

    		if ($this->readElementValue($this->element, $elementValue)) {

    			if ($this->value != $elementValue) {

	    			// Current value is not valid, so return whatever's in the
	    			// element. This can arise when $element is a <select>
	    			return $elementValue;

	    		}
    		}

    	}

    	return $this->value;
    }

    public function __toString() {
    	$element = $this->getElement();
    	return $element->__toString();
    }

    /**
     * @return Octopus_Html_Element The HTML element used to display this filter
     * to the user. Elements returned by this function will
     * have their values automatically kept up-to-date via subsequent calls to
     * val() or setValue().
     */
    protected abstract function createElement();

    /**
     * Called to read the value out of an element returned by a call to
     * createElement().
     * @param $element An HTML element returned by a call to createElement()
     * @param $value Gets set to the current value in $element
     * @return bool True if read is successful, false otherwise.
     */
    protected function readElementValue(Octopus_Html_Element $element, &$value) {

    	if ($element instanceof Octopus_Html_Form_Field) {
    		$value = $element->val();
    		return true;
    	}

    	$value = $element->text();
    	return true;
    }

    /**
     * Called to set the value of an HTML element to $value.
     * @param Octopus_Html_Element $element An element created by a previous
     * call to createElement().
     */
 	protected function setElementValue(Octopus_Html_Element $element, $value) {

 		if ($element instanceof Octopus_Html_Form_Field) {
 			$element->val($value);
 			return;
 		}

 		$element->text($value);
 	}

    protected function &initializeOptions(&$options) {

        if (is_callable($options)) {
            // Allow passing just a function
            $options = array('function' => $options);
        }

        if (!$options) $options = array();

        return $options;
    }

    /**
     * Creates a new filter.
     * @param $table The table this filter is for.
     * @param $type String Kind of filter to create. New filter types can be
     * added by calling Octopus_Html_Table_Filter::register().
     * @param $id String Unique ID for this filter.
     * @param $options Array Any options to pass to the filter's constructor.
     */
    public static function create(Octopus_Html_Table $table, $type, $id, $label, $options = null) {

        if ($id === null) {
            $id = $type;
        }

        if (is_array($label) && $options === null) {
            $options = $label;
            $label = null;
        }

        if (isset(self::$registry[$type])) {
        	$class = self::$registry[$type];
        } else if (class_exists($type)) {
        	$class = $type;
	    } else {

        	$class = 'Octopus_Html_Table_Filter_' . camel_case($type, true);

        	if (!class_exists($class)) {
            	throw new Octopus_Exception("Filter type not registered: $type");
            }

	    }

        return new $class($table, $type, $id, $label, $options);
    }

    public static function register($type, $class) {
        self::$registry[$type] = $class;
    }
}

?>
