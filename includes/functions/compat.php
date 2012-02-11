<?php

if(!function_exists('get_called_class')) {
    function get_called_class() {
        $bt = debug_backtrace();
        $i = 1;

        do {

            if (isset($bt[$i]['object'])) {
                return get_class($bt[$i]['object']);
            }

            if (!isset($bt[$i]['file'])) {
                throw new Octopus_Exception('get_called_class failed.');
            }

            $lines = file($bt[$i]['file']);
            $line = '';
            $lineI = 0;

            // for multiline calls, look back in the file for matching ::function call
            do {
                ++$lineI;
                $line = $lines[$bt[$i]['line'] - $lineI];
            } while (stripos($line, '::' . $bt[$i]['function']) === false);

            preg_match_all('/([a-zA-Z0-9\_]+)::'.$bt[$i]['function'].'/',
                            $line,
                            $matches);


            $class = $matches[1][0];

            $i++;
        } while ($class == 'self');

        return $class;

    }
}

/**
 * bctrunc - Truncates large integer values on 32 systems
 */
function bctrunc($strval, $precision = 0) {
    if (!is_string($strval)) {
        $strval = sprintf('%0.0f', $strval);
    }

    if (false !== ($pos = strpos($strval, '.')) && (strlen($strval) - $pos - 1) > $precision) {
        $zeros = str_repeat("0", $precision);
        return bcadd($strval, "0.{$zeros}0", $precision);
    } else {
        return $strval;
    }
} 
