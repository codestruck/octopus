<?php

    SG::loadClass('SG_Nav_Item');

    /**
     * Class that manages navigation structure and routing for the app.
     */
    class SG_Nav {

        var $_root;
        var $_cache = array();

        function __construct($options = null) {

            //$this->_root = new SG_Nav_Item();

        }

        function add($options, $text = null) {

            //return $this->_root->add($options, $text);


        }

        /**
         * Finds the nav item to use for the given path.
         */
        function find($path, $options = null) {
            //return $this->_root->find($path, $options);
        }

    }

?>
