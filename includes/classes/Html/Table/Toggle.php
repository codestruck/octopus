<?php

Octopus::loadClass('Octopus_Html_Table_Content');

class Octopus_Html_Table_Toggle extends Octopus_Html_Table_Content {

    public static $defaults = array(

        'activeClass' => 'toggleActive',

        'inactiveClass' => 'toggleInactive',

        /**
         * Attribute in which to stash the alternate content.
         */
        'altContentAttr' => 'data-alt'

    );

    public $options;

    private $_id;
    private $_activeLabel, $_inactiveLabel;

    public function __construct($id, $label, $url = null, $options = null) {

        $this->_id = $id;

        $this->_activeLabel = $this->_inactiveLabel = null;
        if (is_array($label)) {

            if (isset($label[0]) && isset($label[1])) {
                list($this->_inactiveLabel, $this->_activeLabel) = $label;
                $label = null;
            } else if (isset($label['active']) && isset($label['inactive'])) {
                $this->_inactiveLabel = $label['inactive'];
                $this->_activeLabel = $label['active'];
                $label = null;
            }
        }

        if ($url === null && $options === null) {

            if (is_array($label)) {
                $options = $label;
                $label = null;
            } else {
                $url = $label;
                $label = null;
            }

        }

        if ($options === null && is_array($url)) {
            $options = $url;
            $url = $label;
            $label = null;
        }

        if ($label === null) {
            $label = humanize($id);
        }

        if ($this->_activeLabel === null) {
            $this->_activeLabel = $label;
        }

        if ($this->_inactiveLabel === null) {
            $this->_inactiveLabel = $label;
        }

        if ($options === null) {
            $this->options = self::$defaults;
        } else {
            $this->options = array_merge(self::$defaults, $options);
        }

        parent::__construct('a', array('href' => $url));
        $this->addClass('toggle', $id);
    }

    /**
     * Renders this bit of content inside the given cell.
     */
    public function fillCell($table, $column, $cell, &$obj) {

        $active = $this->options['activeClass'];
        $inactive = $this->options['inactiveClass'];

        if ($this->isActive($obj)) {
            $this->html($this->_activeLabel);
            $this->setAttribute($this->options['altContentAttr'], $this->_inactiveLabel);
            $this->removeClass($inactive)->addClass($active);
        } else {
            $this->html($this->_inactiveLabel);
            $this->setAttribute($this->options['altContentAttr'], $this->_activeLabel);
            $this->removeClass($active)->addClass($inactive);
        }

        parent::fillCell($table, $column, $cell, $obj);

    }

    public function isActive(&$obj) {

        $id = $this->_id;


        if (is_object($obj)) {
            return isset($obj->$id) && $obj->$id;
        } else if (is_array($obj)) {
            return isset($obj[$id]) && $obj[$id];
        }

    }

}

?>
