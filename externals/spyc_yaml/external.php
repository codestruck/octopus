<?php
require_once($EXTERNAL_DIR . 'spyc-0.4.5/spyc.php');

function load_yaml($file) {
    return spyc_load_file($file);
}

?>