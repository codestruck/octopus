<?php

    /**
     * Checks the sitedir for a file, returning its path. If the file is not
     * found in the sitedir, returns a path to the file in core.
     * @return string The full path to a file.
     */
    function get_file($path) {
    }
    
    function newest_file($file1, $file2 = null) {
        
        if ($file2 === null && is_array($file1)) {
            list($file1, $file2) = $file1;
        }
        
        $m1 = filemtime($file1);
        $m2 = filemtime($file2);
        
        if ($m1 >= $m2) {
            return $file1;
        } else {
            return $file2;
        }
        
    }

?>
