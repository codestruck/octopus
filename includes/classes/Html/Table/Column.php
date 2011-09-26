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
         * Template for content to display in this cell.
         */
        'content' => null,

        /**
         * Whether or not to escape HTML
         */
        'escape' => true,

        /**
         * Function through which to filter values being rendered for this
         * column.
         */
        'function' => null,

        /**
         * Text put in the header cell for this column. The 'title'
         * key is also supported for this. If not set, the column id is
         * humanized and used.
         */
        'label' => null,

        /**
         * Whether this column is sortable. NULL means it should judge for
         * itself whether it should be sortable.
         */
        'sortable' => null,

        /**
         * Column name to use for sorting, if not this one.
         */
        'sortUsing' => null,

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

        if ($this->options['content']) {
            $this->addContent($this->options['content']);
        }

        $this->addClass($id);
    }

    public function addAction($id, $label = null, $url = null, $options = null) {

        Octopus::loadClass('Octopus_Html_Table_Action');

        if ($id instanceof Octopus_Html_Table_Content) {
            $action = $id;
        } else {

            if (is_array($id)) {
                $temp = $id['id'];
                $options = $id;
                $id = $temp;
            }

            if ($options && isset($options['type']) && strcasecmp($options['type'], 'toggle') == 0) {
                return $this->addToggle($id, $label, $url, $options);
            }


            $action = new Octopus_Html_Table_Action($id, $label, $url, $options);
        }

        $this->addContent($action);
        $this->_actions[$action->getContentID()] = $action;

        return $action;
    }

    public function addToggle($id, $labels = null, $url = null, $options = null) {

        Octopus::loadClass('Octopus_Html_Table_Toggle');
        $toggle = new Octopus_Html_Table_Toggle($id, $labels, $url, $options);

        return $this->addAction($toggle);
    }

    public function addContent(/* variable */) {

        $args = func_get_args();

        foreach($args as $arg) {

            if (!$arg) continue;

            if (is_array($arg)) {
                foreach($arg as $a) {
                    $this->addContent($a);
                }
                continue;
            }

            if (!($arg instanceof Octopus_Html_Table_Content)) {
                $arg = new Octopus_Html_Table_Content('', 'span', null, $arg);
            }

            if ($arg) $this->_content[] = $arg;
        }

        return $this;
    }

    public function getAction($id) {
        return isset($this->_actions[$id]) ? $this->_actions[$id] : null;
    }

    public function getActions() {
        return $this->_actions;
    }

    public function addImageAction($id, $image, $alt = null, $url = null, $options = null) {

        // TODO: this

    }

    public function addImageToggle($id, $images, $alts = null, $url = null, $options = null) {

        // TODO: this

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
            if ($this->_sorting && $this->_sorting == OCTOPUS_SORT_ASC) {
                $direction = OCTOPUS_SORT_DESC;
            } else {
                $direction = OCTOPUS_SORT_ASC;
            }
        }

        if ($direction === false) {
            $this->_sorting = false;
            $this->removeClass('sorted');
            return $this;
        }

        if (is_bool($direction) || is_numeric($direction)) {
            $direction = $direction ? OCTOPUS_SORT_ASC : OCTOPUS_SORT_DESC;
        }

        $direction = strtolower($direction);
        $this->_sorting = $direction;
        $this->addClass('sorted');

        return $this;
    }

    /**
     * Applies this column's sorting to the given result set.
     * @return A sorted result set.
     */
    public function sortResultSet($resultSet) {

        if (!$this->isSorted($resultSet)) {
            return $resultSet;
        }

        $sortCol = $this->options['sortUsing'] ? $this->options['sortUsing'] : $this->id;

        return $resultSet->thenOrderBy(array($sortCol => $this->getSorting()));
    }

    public function getSorting() {
        return $this->_sorting;
    }

    public function isSorted(&$dataSource = null) {
        return $this->isSortable($dataSource) && $this->_sorting;
    }

    public function isSortedAsc(&$dataSource = null) {
        return $this->isSorted($dataSource) && (strcasecmp($this->_sorting, OCTOPUS_SORT_ASC) == 0);
    }

    public function isSortedDesc(&$dataSource = null) {
        return $this->isSorted($dataSource) && (strcasecmp($this->_sorting, OCTOPUS_SORT_DESC) == 0);
    }

    /**
     * @return bool Whether this column is sortable against the given datasource.
     */
    public function isSortable(&$dataSource = null) {

        if (!$dataSource) {
            return !empty($this->options['sortable']);
        }

        if ($this->options['sortable'] === null) {
            return $this->shouldBeSortable($dataSource);
        }

        return !!$this->options['sortable'];
    }

    /**
     * @return bool Whether this column should be marked as 'sortable' for the
     * given datasource, all other things being equal.
     */
    public function shouldBeSortable(&$dataSource) {

        if (!empty($this->options['sortUsing'])) {
            return true;
        } else if (class_exists('Octopus_Model_ResultSet') && $dataSource instanceof Octopus_Model_ResultSet) {
            return $this->shouldBeSortableAgainstResultSet($dataSource);
        } else if (is_array($dataSource)) {
            return true;
        } else if (is_string($dataSource)) {
            return true;
        } else {
            return false;
        }

    }

    protected function shouldBeSortableAgainstResultSet($resultSet) {
        $field = $resultSet->getModelField($this->id);
        return !!$field;
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

    /**
     * If no content has been assigned manually to this column, this fills the
     * cell by attempting to read the value from $obj.
     */
    protected function fillCellDefault($td, &$obj) {

        $value = null;
        $id = $this->id;

        // HACK: Octopus_Model does not currently support isset(), so it always
        // reports field values as 'not set'. So don't use the isset() check
        // if it's a model instance.
        $isModel = class_exists('Octopus_Model') && ($obj instanceof Octopus_Model);

        if ($isModel) {

            try
            {
                $value = $obj->$id;
            } catch(Octopus_Exception $ex) {
                // not a valid field
            }

        } else if (is_object($obj) && isset($obj->$id)) {
            $value = $obj->$id;
        } else if (is_array($obj) && isset($obj[$id])) {
            $value = $obj[$id];
        }

        $escape = $this->options['escape'];
        $value = $this->applyFunction($value, $obj, $escape);

        if ($escape) {

            if (is_object($value)) {

                if ($value instanceof Octopus_Model_ResultSet) {
                    $value = $this->resultSetToString($value);
                }

            } else {
                $value = h($value);
            }

        }

        $td->append($value);
    }

    private function resultSetToString($rs) {

        $result = '';
        $count = 0;

        foreach($rs as $model) {
            $result .= '<li>' . htmlspecialchars($model) . '</li>';
            $count++;
        }

        return $result ? "<ul class=\"octopusResultSet octopusCount{$count}\">" . $result . '</ul>' : $result;

    }

    /**
     * Runs any function(s) associated with this column against the given
     * value.
     * @return Mixed The modified value.
     */
    protected function applyFunction(&$value, &$row, &$escape) {

        if (empty($this->options['function'])) {
            return $value;
        }

        $f = $this->options['function'];
        $isString = is_string($f);
        $isObject = is_object($value);

        if ($isString && $isObject && method_exists($value, $f)) {
            // TODO: should there be a way to supply arguments here?
            return $value->$f();
        } else if ($isString && method_exists($this, $f)) {
            return $this->$f($value, $row);
        } else if ($row instanceof Octopus_Model && is_string($f) && method_exists($row, $f)) {
            return $row->$f($value, $row);
        }

        if (is_callable($f)) {

            /* HACK: not all built-in functions like receiving the row as
             *       the 2nd argument.
             *
             * TODO: Have more calling options, something like:
             *
             *      array(
             *          'function' => 'name of function',
             *          'args' => array(OCTOPUS_ARG_VALUE, OCTOPUS_ARG_ROW, $customVariable, new Octopus_Function(...))
             *      )
             */

            $useExtraArgs = true;
            if ($isString) {
                $noExtraArgs = array('htmlspecialchars', 'htmlentities', 'trim', 'ltrim', 'rtrim', 'nl2br', 'basename');
                $useExtraArgs = !in_array($f, $noExtraArgs);
            }

            if ($useExtraArgs) {
                return call_user_func($f, $value, $row, $this);
            } else {
                return call_user_func($f, $value);
            }

        }

        if (is_array($f)) {
	        list($obj, $method) = $f;
	        $f = get_class($obj) . '::' . $method;
	    }

        $escape = false;
        return '<span style="color:red;">Function not found: ' . h($f) . '</span>';

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
        $title = null;

        if (isset($o['label'])) {
        	$title = $o['label'];
        } else if (isset($o['title'])) {
        	$title = $o['title'];
        } else if (isset($o['desc'])) {
        	$title = $o['desc'];
        } else if (isset($o['name'])) {
        	$title = $o['name'];
        }

        if ($title === null) {
        	$title = humanize($id);
        }

        $o['title'] = $title;

        if (empty($o['function']) && !empty($o['func'])) {
            $o['function'] = $o['func'];
            unset($o['func']);
        }

    }
}

?>
