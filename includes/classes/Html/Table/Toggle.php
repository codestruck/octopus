<?php

Octopus::loadClass('Octopus_Html_Table_Content');

class Octopus_Html_Table_Toggle extends Octopus_Html_Table_Content {

    public static $defaults = array(

        'activeClass' => 'toggleActive',

        'inactiveClass' => 'toggleInactive'

    );

    public $options;

    private $_id;

    public function __construct($id, $label, $url = null, $options = null) {

        $this->_id = $id;

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

        if ($options === null) {
            $this->options = self::$defaults;
        } else {
            $this->options = array_merge(self::$defaults, $options);
        }

        parent::__construct('a', array('href' => $url), $label);
        $this->addClass('toggle', $id);
    }

    /**
     * Renders this bit of content inside the given cell.
     */
    public function fillCell($table, $column, $cell, &$obj) {

        $active = $this->options['activeClass'];
        $inactive = $this->options['inactiveClass'];

        if ($this->isActive($obj)) {
            $this->removeClass($inactive)->addClass($active);
        } else {
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
