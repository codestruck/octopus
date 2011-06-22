<?php

Octopus::loadClass('Octopus_Html_Element');
Octopus::loadClass('Octopus_Html_Form_Field');

/**
 * A control for filtering a table's contents.
 */
abstract class Octopus_Html_Table_Filter {

    public static $defaults = array(

        /**
         * Attributes to pass to the element this filter uses.
         */
        'attributes' => array(),

        /**
         * Function used to actually filter results. Receives 2 arguments:
         * the filter text and the datasource being filtered.
         */
        'function' => null,

        /**
         * Function to apply if the data source is a result set. If not
         * specified, the 'function' key is used instead.
         */
        'function_resultset' => null,

        /**
         * Function to apply if the data source is an array. If not
         * specified, the 'function' key is used instead.
         */
        'function_array' => null,

        /**
         * Function to apply if the data source is a sql string. If not
         * specified, the 'function' key is used instead.
         */
        'function_sql' => null,

    );

    private static $registry = array();

    public $id;
    private $type;
    private $label;

    protected $options;
    protected $element;

    public function __construct($type, $id, $label, $options) {

        $this->type = $type;
        $this->id = $id;

        $this->label = ($label === null ? humanize($id) . ':' : $label);

        $options = $this->initializeOptions($options);
        $this->options = array_merge(self::$defaults, $options);

        $this->element = $this->createElement();
    }

    public function __call($name, $arguments) {
        // Forward method calls on to the element.
        return call_user_func_array(array($this->element, $name), $arguments);
    }

    /**
     * Executes this filter against the given data source.
     * @return Mixed A filtered data source.
     */
    public function apply($dataSource) {

        if (class_exists('Octopus_Model_ResultSet') && $dataSource instanceof Octopus_Model_ResultSet) {
            return $this->applyToResultSet($dataSource);
        } else if (is_array($dataSource)) {
            return $this->applyToArray($dataSource);
        } else if (is_string($dataSource)) {
            return $this->applyToSql($dataSource);
        } else {
            throw new Octopus_Exception('Unsupported dataSource: ' . $dataSource);
        }

    }

    protected function callFunction($func, &$data) {
        return call_user_func($func, $this, $data);
    }

    /**
     * Clears the contents of this filter.
     */
    public function clear() {
        return $this->val('');
    }

    public function getType() {
        return $this->type;
    }

    public function createLabelElement() {

        if (!$this->label) {
            return;
        }

        return new Octopus_Html_Element('label', array('class' => 'filterLabel', 'for' => $this->element->id), $this->label);
    }

    /**
     * Executes this filter against a result set.
     */
    protected function applyToResultSet($resultSet) {

        if (isset($this->options['function_resultset'])) {
            return $this->callFunction($this->options['function_resultset'], $resultSet);
        } else if (isset($this->options['function'])) {
            return $this->callFunction($this->options['function'], $resultSet);
        }

        return $resultSet->where($this->id, $this->val());
    }

    /**
     * Executes this filter against an array.
     */
    protected function &applyToArray(&$ar) {

        if (isset($this->options['function_array'])) {
            return $this->callFunction($this->options['function_array'], $array);
        } else if (isset($this->options['function'])) {
            return $this->callFunction($this->options['function'], $array);
        }

        throw new Octopus_Exception("applyToArray is not implemented, and neither the 'function' or 'function_array' options contain a callable function.");
    }

    /**
     * Executes this filter against raw SQL.
     */
    protected function applyToSql($sql) {

        if (isset($this->options['function_sql'])) {
            return $this->callFunction($this->options['function_sql'], $sql);
        } else if (isset($this->options['function'])) {
            return $this->callFunction($this->options['function'], $sql);
        }

        throw new Octopus_Exception("applyToSql is not implemented, and neither the 'function' or 'function_sql' options contain a callable function.");
    }

    /**
     * @return Octopus_Html_Element The actual element that is rendered for
     * this filter.
     */
    abstract protected function createElement();

    protected function &initializeOptions(&$options) {

        if (is_string($options)) {
            // Allow passing just a function
            $options = array('function' => $options);
        }

        if (!$options) $options = array();

        return $options;
    }

    /**
     * Creates a new filter.
     * @param $type String Kind of filter to create. New filter types can be
     * added by calling Octopus_Html_Table_Filter::register().
     * @param $id String Unique ID for this filter.
     * @param $options Array Any options to pass to the filter's constructor.
     */
    public static function create($type, $id, $label, $options = null) {

        if ($id === null) {
            $id = $type;
        }

        if (is_array($label) && $options === null) {
            $options = $label;
            $label = null;
        }

        if (!isset(self::$registry[$type])) {
            throw new Octopus_Exception("Filter type not registered: $type");
        }

        $class = self::$registry[$type];

        return new $class($type, $id, $label, $options);
    }

    public static function register($type, $class) {
        self::$registry[$type] = $class;
    }
}

Octopus::loadClass('Octopus_Html_Table_Filter_Text');
Octopus_Html_Table_Filter::register('text', 'Octopus_Html_Table_Filter_Text');

Octopus::loadClass('Octopus_Html_Table_Filter_Search');
Octopus_Html_Table_Filter::register('search', 'Octopus_Html_Table_Filter_Search');

Octopus::loadClass('Octopus_Html_Table_Filter_Select');
Octopus_Html_Table_Filter::register('select', 'Octopus_Html_Table_Filter_Select');

Octopus::loadClass('Octopus_Html_Table_Filter_Checkbox');
Octopus_Html_Table_Filter::register('checkbox', 'Octopus_Html_Table_Filter_Checkbox');

?>
