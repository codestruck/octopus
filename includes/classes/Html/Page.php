<?php

    SG::loadClass('SG_Base');
    SG::loadClass('SG_Html_Header');

    class SG_Html_Page extends SG_Base {
        
        var $options;
        var $_header = null;
        var $_areas = array();
        
        function __construct($options = null) {
            
            $this->options = $options ? $options : array();
            $this->_header = new SG_Html_Header();
            
        }
        
        function singleton() {
            return SG_Base::base_singleton('SG_Html_Page');
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
