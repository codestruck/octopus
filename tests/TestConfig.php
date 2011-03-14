<?php

    error_reporting(E_ALL | E_STRICT);

    require_once('../includes/core.php');


    $hostname = trim(`hostname`);
    
    switch ($hostname) {
    
        case 'estesm-sole-desktop':
    
            define('DB_hostname', 'localhost');
            define('DB_database', 'octopus_test');
            define('DB_username', 'octopus');
            define('DB_password', 'Cyn1Wruch');
    
            break;
    
    }

?>
