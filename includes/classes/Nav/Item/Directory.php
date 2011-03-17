<?php

    SG::loadClass('SG_Nav_Item');
    SG::loadClass('SG_Nav_Item_File');

    class SG_Nav_Item_Directory : SG_Nav_Item {

        static $defaults = array(

            'filter' = '/\.(php|html?)$/i'
        );

        var $_directory = null;
        var $_children = array();

        /**
         * @return Array the children of this item (all the files in the directory
         */
        function &getChildren() {

            $result = array();

            $filter = $this->_directory . '*';
            foreach(glob($filter) as $file) {

                if (isset($this->_children[$file])) {
                    $result[] = $this->_children[$file];
                    continue;
                }

                if (!preg_match($this->options['filter'], $file)) {
                    continue;
                }

                $item = new SG_Nav_Item_File($this, $file);
                $this->_children[$file] = $item;

                $result[] = $item;
            }

            return $result;
        }

    }

?>
