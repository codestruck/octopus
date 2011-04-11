<?php

    /*
     * All core app functionality comes from here. Any page served up by the
     * app should include this file first.
     */

    define('INCLUDES_DIR', dirname(__FILE__) . '/');
    define('OCTOPUS_DIR', dirname(INCLUDES_DIR) . '/');
    define('ROOT_DIR', dirname(OCTOPUS_DIR) . '/');
    define('FUNCTIONS_DIR', INCLUDES_DIR . 'functions/');
    define('CLASSES_DIR', INCLUDES_DIR . 'classes/');

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

            'path_querystring_arg' => '__path'
        );

        $options = $options ? array_merge($defaults, $options) : $defaults;

        ////////////////////////////////////////////////////////////////////////
        // Core function includes
        ////////////////////////////////////////////////////////////////////////

        require_once(FUNCTIONS_DIR . 'debug.php');
        require_once(FUNCTIONS_DIR . 'misc.php');
        require_once(FUNCTIONS_DIR . 'strings.php');
        require_once(FUNCTIONS_DIR . 'files.php');
        require_once(FUNCTIONS_DIR . 'http.php');
        require_once(FUNCTIONS_DIR . 'html.php');
        require_once(FUNCTIONS_DIR . 'compat.php');

        ////////////////////////////////////////////////////////////////////////
        // Spin up an app instance
        ////////////////////////////////////////////////////////////////////////

        require_once(OCTOPUS_DIR . 'includes/classes/SG.php');

        SG::loadClass('SG_App');

        $app = SG_App::start($options);

    }

    /**
     * Once the app has been bootstrapped, renders a page.
     * @param $path String Page to render. If null, the __path querystring arg
     * is used.
     */
    function render_page($path = null) {

        $app = SG_App::singleton();
        $response = $app->getResponse($path);
        $response->flush();

    }

?>
