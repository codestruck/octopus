<?php

SG::loadClass('SG_Nav_Item');
SG::loadClass('SG_Nav_Item_File');

class SG_Nav_Item_Directory : SG_Nav_Item {

    static $defaults = array(
        'filter' = '/\.(php|html?)$/i'
    );

    var $_directory = null;
    var $_children = null;


    protected function getDefaultText() {

        $text = basename($this->_directory);
        $text = preg_replace('/\..*?$/', '', $text);
        $text = preg_replace('/[_-]/', ' ', $text);
        $text = preg_replace('/\s{2,}/', ' ', $text);
        $text = ucwords($text);

        return $text;
    }


    /**
     * @return Array the children of this item (all the files in the
     * directory).
     */
    public function &getChildren() {

        if ($this->_children) {
            return $this->_children;
        }

        $this->_children = array();

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
