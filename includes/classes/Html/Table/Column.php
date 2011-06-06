<?php

Octopus::loadClass('Octopus_Html_Element');
Octopus::loadClass('Octopus_Html_Table_Content');

define('OCTOPUS_SORT_ASC', 'asc');
define('OCTOPUS_SORT_DESC', 'desc');

/**
 * A single column in a table.
 */
class Octopus_Html_Table_Column {

    public static $defaults = array(

        /**
         * Whether this column is sortable.
         */
        'sortable' => false,


    );

    public $id;
    public $options;

    protected $table;

    private $_sorting = false;
    private $_cell;
    private $_content = array();
    private $_actions = array();

    public function __construct($id, $options, $table) {
        $this->table = $table;
        $this->id = $id;
        $this->options = empty($options) ? self::$defaults : array_merge(self::$defaults, $options);
        $this->_cell = new Octopus_Html_Element('td');
        self::initializeOptions($id, $this->options);

        $this->addClass($id);
    }

    public function addAction($id, $label, $url = null, $options = null) {

        Octopus::loadClass('Octopus_Html_Table_Action');

        $action = new Octopus_Html_Table_Action($id, $label, $url, $options);

        $this->_content[] = $action;
        $this->_actions[$id] = $action;

        return $action;
    }

    public function addToggle($id, $label, $url = null, $options = null) {

        Octopus::loadClass('Octopus_Html_Table_Toggle');

        $toggle = new Octopus_Html_Table_Toggle($id, $label, $url, $options);

        $this->_content[] = $toggle;
        $this->_actions[$id] = $toggle;

        return $this;
    }


    public function append($content) {
        $this->_content[] = $content;
        return $this;
    }

    public function getAttribute($attr, $default = null) {
        return $this->_cell->getAttribute($attr, $default);
    }

    public function setAttribute($attr, $value = null) {
        $this->_cell->setAttribute($attr, $value);
        return $this;
    }

    public function getAttributes() {
        return $this->_cell->getAttributes();
    }

    /**
     * Adds one or more CSS classes to cells in this column.
     */
    public function addClass(/* polymorphic */) {
        $args = func_get_args();
        call_user_func_array(array($this->_cell, 'addClass'), $args);
        return $this;
    }

    /**
     * Removes one or more classes from all cells in this column.
     */
    public function removeClass(/* polymorphic */) {
        $args = func_get_args();
        call_user_func_array(array($this->_cell, 'removeClass'), $args);
    }

    /**
     * Gets and sets style properties on cells in this column.
     */
    public function css(/* polymorphic */) {

        $args = func_get_args();

        switch(count($args)) {

            case 0:
                return $this;

            case 1:

                $arg = array_shift($args);

                if (is_array($arg)) {
                    $this->_cell->css($arg);
                    return $this;
                } else {
                    return $this->_cell->css($arg);
                }

            default:

                call_user_func_array(array($this->_cell, 'css'), $args);
                return $this;

        }

    }

    /**
     * Sorts this column.
     */
    public function sort($direction = null) {

        if ($direction === null) {
            // If called w/o argument, flip sorting.
            if ($this->isSortedDesc()) {
                $direction = OCTOPUS_SORT_ASC;
            } else {
                $direction = OCTOPUS_SORT_DESC;
            }
        }

        if ($direction === false) {
            $this->_sorting = false;
            $this->removeClass('sorted', OCTOPUS_SORT_ASC, OCTOPUS_SORT_DESC);
            return $this;
        }

        if ((is_bool($direction) || is_numeric($direction)) && $direction) {
            $direction = OCTOPUS_SORT_ASC;
        }

        $direction = strtolower($direction);
        $this->addClass('sorted');

        switch($this->_sorted = $direction) {

            case OCTOPUS_SORT_DESC:
                $this->_sorted = OCTOPUS_SORT_DESC;
                $this->removeClass(OCTOPUS_SORT_ASC);
                $this->addClass(OCTOPUS_SORT_DESC);
                return $this;

            default:
                $this->_sorted = OCTOPUS_SORT_ASC;
                $this->removeClass(OCTOPUS_SORT_DESC);
                $this->addClass(OCTOPUS_SORT_ASC);


        }

        return $this;
    }

    public function getSorting() {
        return $this->_sorting;
    }

    public function isSorted() {
        return !!$this->_sorting;
    }

    public function isSortedAsc() {
        return $this->_sorting && strcasecmp($this->_sorting, OCTOPUS_SORT_ASC) == 0;
    }

    public function isSortedDesc() {
        return $this->_sorting && strcasecmp($this->_sorting, OCTOPUS_SORT_DESC) == 0;
    }

    public function isSortable() {
        return $this->options['sortable'];
    }

    /**
     * Puts the content for $obj into the given cell.
     */
    public function fillCell($td, &$obj) {

        if (!$obj) {
            return;
        }

        if (empty($this->_content)) {
            $this->fillCellDefault($td, $obj);
            return;
        }

        foreach($this->_content as $item) {
            $item->fillCell($this->table, $this, $td, $obj);
        }

    }

    private function fillCellDefault($td, &$obj) {

        $value = null;
        $id = $this->id;

        if (is_object($obj) && isset($obj->$id)) {
            $value = $obj->$id;
        } else if (is_array($obj) && isset($obj[$id])) {
            $value = $obj[$id];
        }

        $td->append($value);
    }

    public function title(/* $title */) {

        if (func_num_args() == 0) {
            return empty($this->options['title']) ? '' : $this->options['title'];
        } else {
            $this->options['title'] = func_get_arg(0);
            return $this;
        }

    }


    /**
     * Normalizes options passed to this column;
     */
    private static function initializeOptions($id, &$o) {

        // Shim over some old column parameters
        if (!empty($o['nosort'])) {
            $o['sortable'] = false;
            unset($o['nosort']);
        }

        // Normalize some field props
        if (empty($o['title'])) {

            if (!empty($o['desc'])) {
                $o['title'] = $o['desc'];
            } else if (!empty($o['label'])) {
                $o['title'] = $o['label'];
            } else if (!empty($o['name'])) {
                $o['title'] = $o['name'];
            } else {
                $o['title'] = humanize($id);
            }


        }

    }
}

?>
