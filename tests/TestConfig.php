<?php

    error_reporting(E_ALL | E_STRICT);
    define('DEV', true);

    require_once(dirname(__FILE__) . '/../includes/core.php');
    bootstrap(array('use_site_config' => false));
    error_reporting(E_ALL | E_STRICT);

    $hostname = trim(`hostname`);
    define('TEST_FIXTURE_DIR', dirname(__FILE__) . '/fixtures/');
    require_once('SG_DB_TestCase.php');
    require_once('SG_App_TestCase.php');

    // put testing helpers somewhere
    function table_count($table) {
        $s = new SG_DB_Select();
        $s->table($table);
        $query = $s->query();

        return $query->numRows();
    }

    switch ($hostname) {

        case 'estesm-sole-desktop':
        case 'estesm-laptop':

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

        case 'server1.solepure.com':

            define('DB_hostname', 'localhost');
            define('DB_database', 'solegrap_octopus');
            define('DB_username', 'solegrap_general');
            define('DB_password', '58#a3e*7h9hmubpfkhec4du!6754hu');

            break;

    }

?>
