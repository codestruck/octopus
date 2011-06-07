<?php

Octopus::loadClass('Octopus_Html_Table_Action');

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
    private $_activeContent, $_inactiveContent;

    public function __construct($id, $labels, $url = null, $options = null) {

        parent::__construct($id, null, $url, $options);

        $this->_activeContent = $this->_inactiveContent = null;

        if (is_array($labels)) {

            if (isset($labels[0]) && isset($labels[1])) {
                list($this->_inactiveContent, $this->_activeContent) = $labels;
                $labels = null;
            } else if (isset($labels['active']) && isset($labels['inactive'])) {
                $this->_inactiveContent = $labels['inactive'];
                $this->_activeContent = $labels['active'];
                $labels = null;
            }

        }

        if ($url === null && $options === null) {

            if (is_array($labels)) {
                $options = $labels;
                $labels = null;
            } else {
                $url = $labels;
                $labels = null;
            }

        }

        if ($options === null && is_array($url)) {
            $options = $url;
            $url = $labels;
            $labels = null;
        }

        if ($labels === null) {
            $labels = humanize($id);
        }

        if ($options === null) {
            $this->options = self::$defaults;
        } else {
            $this->options = array_merge(self::$defaults, $options);
        }

        parent::__construct($id, 'a', array('href' => $url));
        $this->addClass('toggle', $id);
    }

    /**
     * Renders this bit of content inside the given cell.
     */
    public function fillCell($table, $column, $cell, &$obj) {

        $active = $this->options['activeClass'];
        $inactive = $this->options['inactiveClass'];

        if ($this->isActive($obj)) {
            $this->html($this->_activeContent);
            $this->setAttribute($this->options['altContentAttr'], $this->_inactiveContent);
            $this->removeClass($inactive)->addClass($active);
        } else {
            $this->html($this->_inactiveContent);
            $this->setAttribute($this->options['altContentAttr'], $this->_activeContent);
            $this->removeClass($active)->addClass($inactive);
        }

        parent::fillCell($table, $column, $cell, $obj);

    }


    public function isActive(&$obj) {

        $id = $this->getContentID();

        if (is_object($obj)) {
            return isset($obj->$id) && $obj->$id;
        } else if (is_array($obj)) {
            return isset($obj[$id]) && $obj[$id];
        }

    }

}

?>
