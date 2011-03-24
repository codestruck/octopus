<?php

if(!function_exists('get_called_class')) {
    function get_called_class() {
        $bt = debug_backtrace();
        $i = 1;

        do {

            if (isset($bt[$i]['object'])) {
                return get_class($bt[$i]['object']);
            }

            $lines = file($bt[$i]['file']);

            preg_match_all('/([a-zA-Z0-9\_]+)::'.$bt[$i]['function'].'/',
                            $lines[$bt[$i]['line']-1],
                            $matches);

            $class = $matches[1][0];

            $i++;
        } while ($class == 'self');

        return $class;

    }
}

?>
