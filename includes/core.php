<?php
/**
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */

/*
 * insert millisecend level startup time
 */
$_SERVER['REQUEST_TIME_MILLISECOND'] = microtime(true);

if (!defined('E_DEPRECATED')) {
    define('E_DEPRECATED', 8192);
}

/*
 * Flash will send the HTTP_HOST with the port in it on mac
 */
if (isset($_SERVER['HTTP_HOST']) && $pos = strpos($_SERVER['HTTP_HOST'], ':')) {
    $_SERVER['HTTP_HOST'] = substr($_SERVER['HTTP_HOST'], 0, $pos);
}

/*
 * All core app functionality comes from here. Any page served up by the
 * app should include this file first.
 */
define('OCTOPUS_INCLUDES_DIR', dirname(__FILE__) . '/');
define('OCTOPUS_DIR', dirname(OCTOPUS_INCLUDES_DIR) . '/');
define('OCTOPUS_FUNCTIONS_DIR', OCTOPUS_INCLUDES_DIR . 'functions/');
define('OCTOPUS_CLASSES_DIR', OCTOPUS_INCLUDES_DIR . 'classes/');
define('OCTOPUS_EXTERNALS_DIR', OCTOPUS_DIR . 'externals/');

if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(OCTOPUS_DIR) . '/');
}

////////////////////////////////////////////////////////////////////////
// Core function includes
////////////////////////////////////////////////////////////////////////

require_once(OCTOPUS_FUNCTIONS_DIR . 'debug.php');
require_once(OCTOPUS_FUNCTIONS_DIR . 'misc.php');
require_once(OCTOPUS_FUNCTIONS_DIR . 'strings.php');
require_once(OCTOPUS_FUNCTIONS_DIR . 'numbers.php');
require_once(OCTOPUS_FUNCTIONS_DIR . 'dates.php');
require_once(OCTOPUS_FUNCTIONS_DIR . 'files.php');
require_once(OCTOPUS_FUNCTIONS_DIR . 'http.php');
require_once(OCTOPUS_FUNCTIONS_DIR . 'html.php');
require_once(OCTOPUS_FUNCTIONS_DIR . 'db.php');
require_once(OCTOPUS_FUNCTIONS_DIR . 'themes.php');
require_once(OCTOPUS_FUNCTIONS_DIR . 'compat.php');

require_once(OCTOPUS_CLASSES_DIR . 'Octopus.php');

require_once(OCTOPUS_FUNCTIONS_DIR . 'security.php');

/**
 * Spins up a new instance of the application.
 * @param $options array Bootstrapping options for the app.
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 * @todo Make this Octopus::bootstrap() or Octopus::init() or
 * Octopus::configure()
 */
function bootstrap($options = null) {

    $defaults = array(

        /**
         * Whether or not to load site-specific configuration files.
         */
        'use_site_config' =>    true,

        'path_querystring_arg' => '__path',

        'start_app' => true,

    );

    $options = $options ? array_merge($defaults, $options) : $defaults;

    if (defined('PRIVATE_DIR')) {
        define('OCTOPUS_PRIVATE_DIR', PRIVATE_DIR);
    } else {
        define('OCTOPUS_PRIVATE_DIR', ROOT_DIR . '_private/');
    }

    ////////////////////////////////////////////////////////////////////////
    // Spin up an App instance
    ////////////////////////////////////////////////////////////////////////

    if ($options['start_app']) {
        Octopus_App::start($options);
    }

}

/**
 * Once the app has been bootstrapped, renders a page.
 * @param $path String Page to render. If null, the __path querystring arg
 * is used.
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 * @TODO Make this Octopus::renderPage($path)
 */
function render_page($path = null) {

    $app = Octopus_App::singleton();

    $response = $app->getResponse($path);
    $response->render();


}
