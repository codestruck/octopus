<?php

/**
 * An SG_Nav_Item based on a physical file.
 */
class SG_Nav_Item_File extends SG_Nav_Item {

    var $_file;

    protected function getDefaultText() {

        /*
        TODO: read any title set from the file.
        $oldTitle = whatever_get_title();
        whatever_set_title('');
        ob_start();
        include($this->_file);
        ob_end();
        $text = whatever_get_title();
        whatever_set_title($oldTitle);
        if ($text) return $text;
        */

        // Make a nice name out of the file name
        $text = basename($this->_file);
        $text = preg_replace('/\..*?$/', '', $text);
        $text = preg_replace('/[_-]/', ' ', $text);
        $text = preg_replace('/\s{2,}/', ' ', $text);
        $text = ucwords($text);

        return $text;
    }


}

?>
