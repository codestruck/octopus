<?php

    /**
     * A chunk of a page.
     */
    class Octopus_Html_Area {

        static $defaults = array(
            'display_order' => 0
        );

        function __construct($name, $options = null) {

            if (is_numeric($options)) {
                $options = array('display_order' => $options);
            }

        }

        /**
         * Returns the HTML to display for this area.
         */
        function getHtml() {

        }

    }

