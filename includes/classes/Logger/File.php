<?php

class Octopus_Logger_File {

    function __construct($file) {
        $this->file = $file;
        $this->_handle = null;
    }

    function _open() {

        if (!$this->_handle = @fopen($this->file, 'a')) {
            throw new Octopus_Exception("Can't open log file: $this->file.");
        }

        chmod($this->file, 0666);

    }

    function log($line) {

        if (!$this->_handle) {
            $this->_open();
        }

        if ($this->_handle) {
            $line = date('r') . ': ' . $line;
            fwrite($this->_handle, $line . "\n");
        }

    }

}
