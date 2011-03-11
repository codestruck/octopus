<?php

// try to alphabetize?

    /**
     * Define something only if it's not already
     */
    function define_unless($constant, $value) {
        if (!defined($constant)) {
            define($constant, $value);
            return true;
        }
        return false;
    }
    
    
    /**
     * Helper for reading $_GET.
     * @return mixed The value of $_GET[$arg] if present, $default otherwise, 
     * or, if called w/o args, whether or not there's anything in $_GET.
     */
    function get($arg = null, $default = null) {
        
        if ($arg === null && $default === null) {
            return count($_GET);
        }
        
        return isset($_GET[$arg]) ? $_GET[$arg] : $default;
    }

    /**
     * Helper for reading $_POST.
     * @return mixed The value of $_POST[$arg] if present, $default otherwise, 
     * or, if called w/o args, whether or not there's anything in $_POST.
     */        
    function post($arg = null, $default = null) {
        
        if ($arg === null && $default === null) {
            return count($_POST);
        }
        
        return isset($_POST[$arg]) ? $_POST[$arg] : $default;
    }

?>
