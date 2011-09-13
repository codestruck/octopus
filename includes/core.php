<?php

    /*
     * insert millisecend level startup time
     */
    $_SERVER['REQUEST_TIME_MILLISECOND'] = microtime(true);

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
    require_once(OCTOPUS_FUNCTIONS_DIR . 'themes.php');
    require_once(OCTOPUS_FUNCTIONS_DIR . 'compat.php');

    require_once(OCTOPUS_DIR . 'includes/classes/Octopus.php');

    require_once(OCTOPUS_FUNCTIONS_DIR . 'security.php');

    /**
     * Spins up a new instance of the application.
     *
     * @param $options array Bootstrapping options for the app.
     */
    function bootstrap($options = null) {

        $defaults = array(

            /**
             * Whether or not to load site-specific configuration files.
             */
            'use_site_config' =>    true,

            'path_querystring_arg' => '__path',

            'start_app' => true,

            'handle_exceptions' => true,
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

            if ($options['handle_exceptions']) {
                if (set_exception_handler('octopus_handle_exception') !== null) {
                    restore_exception_handler();
                }
            }

            Octopus::loadClass('Octopus_App');
            Octopus_App::start($options);
        }

    }

    /**
     * Once the app has been bootstrapped, renders a page.
     * @param $path String Page to render. If null, the __path querystring arg
     * is used.
     */
    function render_page($path = null) {

        $app = Octopus_App::singleton();
        
        if ($app->DEV) {
            // In dev mode, use buffered output and add extra debugging info
            $response = $app->getResponse($path, true);
            $renderTime = round(microtime(true) - $_SERVER['REQUEST_TIME_MILLISECOND'], 3);
            $response->replaceContent('<!-- OF_OCTOPUS_TOTAL_RENDER_TIME -->', ' of ' . $renderTime);
            $response->replaceContent('<!-- OCTOPUS_TOTAL_RENDER_TIME -->', $renderTime);

        } else {
            // Otherwise, just write out as we have data.
            $response = $app->getResponse($path);
        }

        $response->flush();

    }

    /**
     * Global exception handler.
     */
    function octopus_handle_exception($ex) {
        dump_r($ex);
        die();
    }

?>
