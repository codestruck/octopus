<?php

class SG_Logger_File {

    function SG_Logger_File($file) {
        $this->file = $file;
        $this->_handle = null;
    }

    function _open() {

        if (!$this->_handle = @fopen($this->file, 'a')) {
            print "Can't open log file: $this->file.";
        }

    }

    function log($line) {

        if (!$this->_handle) {
            $this->_open();
        }

        if ($this->_handle) {
            $line = date('r') . ': ' . $line;
            @fwrite($this->_handle, $line . "\n");
        }

    }

}

?>