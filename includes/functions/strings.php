<?php

    function end_in($end, $str) {
    
        $len = strlen($str);
    
        if ($len > 0 && substr($str, $len - strlen($end)) != $end) {
            $str .= $end;
        }
    
        return $str;
    
    }
    
    function start_in($start, $str) {
    
        if (strncmp($start, $str, strlen($start)) != 0) {
            return $start . $str;
        } else {
            return $str;
        }
    }
    
    /**
     * Turns URLs in $s into hyperlinks.
     */
    function linkify($s) {
        // TODO implement
    }
    
    /**
     * Cleans up text from unknown sources (like ie from word).
     */
    function decruftify($s) {
        
        /*
         * Things this could do:
         *  - normalize whitespace characters
         *  - Clean up curly quotes
         *  - Return nice UTF8
         */
        
    }

    /**
     * Pluralizes a singular noun. Doesn't try too hard.
     */
    function pluralize($x) {
     
        $x = preg_replace('/y$/i', 'ies', $x, 1, $count);
        if ($count) return $x;
        
        return $x . 's';
    }
    
    /**
     * Converts a camelCased string to an underscore_separated_string
     */
    function underscore($s) {
        
        $s = preg_replace('/([a-z])([A-Z]+)/', '$1_$2', $s);
        $s = preg_replace('/\s+/', '_', $s);
        return strtolower($s);
        
    }

    /**
     * Converts an arbitrary string into a valid css class;
     */
    function to_css_class($x) {
        
        $x = trim($x);
        $x = preg_replace('/[^a-z0-9-]/i', '-', $x);
        $x = preg_replace('/-{2,}/', '-');
        $x = preg_replace('/^([^a-z-])/i', '-$1', $x); 
        
        return $x;
    }
    
    /**
     * Converts an arbitrary string into a valid slug.
     */
    function to_slug($x) {
        
        $x = strtolower(trim($x));
        $x = str_replace('&', ' and ', $x);
        $x = preg_replace('/[\'"\(\)]/', '', $x);
        $x = preg_replace('/[^a-z0-9-]/i', '-', $x);
        $x = preg_replace('/-{2,}/', '-', $x);
        return trim($x, '-');
    }
    

?>
