<?php

    error_reporting(E_ALL | E_STRICT);

    require_once(dirname(__FILE__) . '/../includes/core.php');
    bootstrap(false);

    $hostname = trim(`hostname`);
    
    switch ($hostname) {
    
        case 'estesm-sole-desktop':
    
            define('DB_hostname', 'localhost');
            define('DB_database', 'octopus_test');
            define('DB_username', 'octopus');
            define('DB_password', 'Cyn1Wruch');
    
            break;
    
        case 'hinz-laptop.local':

            define('DB_hostname', 'localhost');
            define('DB_database', 'sole_octopus_test');
            define('DB_username', 'sg');
            define('DB_password', 'fruitl00ps');
    
            break;
            
            
    }

?>
