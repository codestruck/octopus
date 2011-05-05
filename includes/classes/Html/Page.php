<?php

Octopus::loadClass('Octopus_Base');
Octopus::loadClass('Octopus_Html_Header');

class Octopus_Html_Page extends Octopus_Base {

    private $_navItem;


    function __construct($navItem, $options = null) {

        $options = $options ? $options : array();

        if (is_array($navItem)) {
        }

        $this->options = $options ? $options : array();

    }

    function singleton() {
        return Octopus_Base::base_singleton('Octopus_Html_Page');
    }



}


?>
