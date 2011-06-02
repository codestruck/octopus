<?php

    // Boo-urns

    if (!defined('PEAR_DIR')) define('PEAR_DIR', dirname(__FILE__) . '/PEAR/');


    set_include_path(get_include_path() . ':' . PEAR_DIR);

    require_once(PEAR_DIR . 'PEAR.php');

?>
