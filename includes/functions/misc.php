<?php

// try to alphabetize?

/*
 * Define something only if it's not already
 */
function define_unless($constant, $value) {
    if (!defined($constant)) {
        define($constant, $value);
    }
}

/*
 * dump_r($a) - wrapper for print_r that prints <pre> tags
 */
function dump_r($obj) {
    if (isset($_SERVER['HTTP_USER_AGENT'])) {
        print '<pre style="text-align: left;clear: both;background-color: white;color:black;border: 1em solid grey; padding: 2em; margin: 2em;z-index:100000000;positi
on: relative;">';
    }
    print_r($obj);
    if (isset($_SERVER['HTTP_USER_AGENT'])) {
        print '</pre>';
    } else {
        print "\n";
    }
}

function end_in($end, $str) {

    $len = strlen($str);

    if ($len > 0 && substr($str, $len) != $end) {
        $str .= $end;
    }

    return $str;

}

function start_in($start, $str) {

    $len = strlen($str);

    if ($len > 0 && substr($str, 0, $len) != $start) {
        $str = $start . $str;
    }

    return $str;

}


?>
