<?php

class Octopus_Options {

    private $values = array();
    private $defines = false;

    public function __construct() {
        foreach(func_get_args() as $arg) {
            $this->add($arg);
        }
    }


    public function add($options) {

    }

    public function __get($name) {

    }

    public function __set($name, $value) {

    }

    public function set($name, $value = null) {

    }

    public function get($name) {

    }

    /**
     *
     */
    public function toArray() {

    }

    /**
     *
     * @return $this
     */
    public function useDefines() {

    }

}

function o() {

}

