<?php

define('OCTOPUS_SORT_ASC', 'asc');
define('OCTOPUS_SORT_DESC', 'desc');

/**
 * A single column in a table.
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
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

    private $_sort = null; // null = no sort, true = sort asc, false = sort desc
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

        $this->addClass(to_css_class($id));
    }

    public function addAction($id, $label = null, $url = null, $options = null) {

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

    /**
     * @return $dataSource, with any sorting for this column applied.
     */
    public function applySorting(Octopus_DataSource $dataSource) {

        if ($this->isSorted($dataSource)) {
            return $dataSource->sort($this->id, $this->isSortedAsc($dataSource), false);
        }

        return $dataSource;
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
     * @param Mixed $direction. The direction to sort. Possible values are:
     *
     *    ascending - true or 'asc',
     *  descending - false or 'desc',
     *
     * If no argument is supplied, the column is sorted ascending if it is not
     * already sorted, or the sort direction is inverted.
     */
    public function sort($direction = null) {

        if ($direction === null) {

            if ($this->_sort === null) {
                $direction = true;
            } else {
                $direction = !$this->_sort;
            }

        }

        if (is_string($direction)) {
            $direction = Octopus_Html_Table::parseSortDirection($direction);
        }

        $this->_sort = !!$direction;
        $this->addClass('sorted');

        return $this;
    }

    /**
     * Removes any sorting applied to this column.
     */
    public function unsort() {
        $this->_sort = null;
        $this->removeClass('sorted');
        return $this;
    }
    public function isSorted($dataSource = null) {
        return $this->_sort !== null && $this->isSortable($dataSource);
    }

    public function isSortedAsc($dataSource = null) {
        return $this->isSorted($dataSource) && ($this->_sort === true);
    }

    public function isSortedDesc($dataSource = null) {
        return $this->isSorted($dataSource) && ($this->_sort === false);
    }

    /**
     * @return bool Whether this column is sortable against the given datasource.
     */
    public function isSortable($dataSource = null) {

        if (!$dataSource) {
            return !empty($this->options['sortable']);
        }

        if ($this->options['sortable'] === null) {
            return $dataSource->isSortable($this->id);
        }

        return !!$this->options['sortable'];
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
            $result .= '<li>' . h($model) . '</li>';
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

        return Octopus_Html_Table_Content::applyFunction($this->options['function'], $value, $row, $escape, $this);

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

