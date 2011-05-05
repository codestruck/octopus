<?php

SG::loadClass('SG_Base');
SG::loadClass('SG_Html_Header');

class SG_Html_Page extends SG_Base {

    private $_navItem;


    function __construct($navItem, $options = null) {

        $options = $options ? $options : array();

        if (is_array($navItem)) {
        }

        $this->options = $options ? $options : array();

    }

    function singleton() {
        return SG_Base::base_singleton('SG_Html_Page');
    }



}


?>
