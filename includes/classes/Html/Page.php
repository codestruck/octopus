<?php

    Octopus::loadClass('Octopus_Base');
    Octopus::loadClass('Octopus_Html_Header');

    class Octopus_Html_Page extends Octopus_Base {
        
        var $options;
        var $_header = null;
        var $_areas = array();
        
        function __construct($options = null) {
            
            $this->options = $options ? $options : array();
            $this->_header = new Octopus_Html_Header();
            
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
