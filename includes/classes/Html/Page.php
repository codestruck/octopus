<?php

Octopus::loadClass('Octopus_Base');
Octopus::loadClass('Octopus_Html_Header');

class Octopus_Html_Page extends Octopus_Base {

    private $_navItem;


    function __construct($navItem, $options = null) {

        $options = $options ? $options : array();

        if (is_array($navItem)) {

        }
    }
    function singleton() {
        return Octopus_Base::base_singleton('Octopus_Html_Page');
    }

    /**
     * Adds content to the page.
     */
    function add($area, $content) {

        if (!isset($this->_areas[$area])) {
            $this->_areas[$area] = array();
        }

        $this->_areas[$area][] = $content;

    }

    /**
     * Outputs the page's content.
     */
    function render($return = false) {
        if ($return) {
            return $this->__toString();
        } else {
            echo $this->__toString();
        }
    }

    /**
     * Returns the full HTML for the page.
     */
    function __toString() {

    }


}


?>
