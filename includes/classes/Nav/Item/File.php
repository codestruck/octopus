<?php

/**
 * An Octopus_Nav_Item based on a physical file.
 */
class Octopus_Nav_Item_File extends Octopus_Nav_Item {

    private $_file;
    private $_path;
    private $_text;

    public function __construct($file) {
        parent::__construct();
        $this->_file = $file;

        $file = basename($file);
        $pos = strrpos($file, '.');
        if ($pos !== false) $file = substr($file, 0, $pos);

        $this->_path = $file;
    }

    public function getPath() {
        return $this->_path;
    }

    public function getFile() {
        return $this->_file;
    }

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

        if ($this->_text) {
            return $this->_text;
        }

        // Make a nice name out of the file name
        $text = basename($this->_file);
        $text = preg_replace('/\..*?$/', '', $text);
        $text = preg_replace('/[_-]/', ' ', $text);
        $text = preg_replace('/\s{2,}/', ' ', $text);
        $text = ucwords($text);

        return $this->_text = $text;
    }


}

?>
