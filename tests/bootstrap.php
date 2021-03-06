<?php
/**
 * This file is automatically included by the Octopus test runner.
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */

    if (function_exists('xdebug_disable')) {
        xdebug_disable();
    }

    date_default_timezone_set('America/Los_Angeles');
    error_reporting(E_ALL | E_STRICT);

    require_once(dirname(__FILE__) . '/../includes/core.php');

    if (!defined('OCTOPUS_TESTING_SITE')) define('OCTOPUS_TESTING_SITE', false);

    if (!OCTOPUS_TESTING_SITE) {
        define('DEV', true);
        // TODO: Allow passing config file path to bootstrap() below
        $configFile = dirname(__FILE__) . '/../test_config.php';
        if (is_file($configFile)) {
            require_once($configFile);
        } else {
            define('DB_username', 'octopus');
            define('DB_password', 'octopus');
            define('DB_database', 'octopus');
            define('DB_hostname', 'localhost');
        }
    }

    bootstrap(array(
        'use_site_config' =>     OCTOPUS_TESTING_SITE,
        'use_defines' =>         OCTOPUS_TESTING_SITE,
        'create_dirs' =>         OCTOPUS_TESTING_SITE,
    ));

    require_once('Octopus_DB_TestCase.php');
    require_once('Octopus_App_TestCase.php');
    require_once('Octopus_Html_TestCase.php');


    /**
     * @internal
     * @TODO Put testing helpers somewhere
     */
    function table_count($table) {
        $s = new Octopus_DB_Select();
        $s->table($table);
        $query = $s->query();

        return $query->numRows();
    }

    if (!OCTOPUS_TESTING_SITE) {
           __octopus_init_system_tests();
    }

    /**
     * @internal
     */
    function __octopus_init_system_tests() {

        $db = Octopus_DB::singleton();
        $db->query('SET storage_engine=INNODB', true);

        require_once('fixtures/model/models.php');
        require_once('schema.php');
        run_drops();
        run_creates();

    }
