<?php

    /*
     * All core app functionality comes from here. Any page served up by the
     * app should include this file first.
     */
         
    /**
     * Spins up a new instance of the application.
     *
     * @param $useSiteConfig bool Whether or not to include the site-specific config file.
     */
    function bootstrap($useSiteConfig = true) {
     
        global $URL_BASE;
        global $ROOT_DIR, $SITE_DIR, $INCLUDES_DIR, $FUNCTIONS_DIR, $CLASSES_DIR;
        
        ////////////////////////////////////////////////////////////////////////
        // Directory Configuration
        ////////////////////////////////////////////////////////////////////////
        
        define('ROOT_DIR', $ROOT_DIR = dirname(dirname(__FILE__)) . '/');
        define('SITE_DIR', $SITE_DIR = $ROOT_DIR . '/site/');
        define('INCLUDES_DIR', $INCLUDES_DIR = $ROOT_DIR . '/includes/');
        define('FUNCTIONS_DIR', $FUNCTIONS_DIR = $INCLUDES_DIR . '/functions/');
        define('CLASSES_DIR', $CLASSES_DIR = $INCLUDES_DIR . '/classes/');

        ////////////////////////////////////////////////////////////////////////
        // Core Includes
        ////////////////////////////////////////////////////////////////////////
        
        require_once(FUNCTIONS_DIR . 'debug.php');
        require_once(FUNCTIONS_DIR . 'misc.php');
        require_once(FUNCTIONS_DIR . 'strings.php');
        require_once(FUNCTIONS_DIR . 'files.php');
        require_once(FUNCTIONS_DIR . 'http.php');
        
        require_once(CLASSES_DIR .'SG.php');
        
        ////////////////////////////////////////////////////////////////////////
        // WHERE ARE WE?
        ////////////////////////////////////////////////////////////////////////
        
        define('URL_BASE', $URL_BASE = find_url_base());
        
        ////////////////////////////////////////////////////////////////////////
        // Site-Specific Configuration
        ////////////////////////////////////////////////////////////////////////
        
        if ($useSiteConfig) {
            require_once(SITE_DIR . 'config.php');
        }
        
        ////////////////////////////////////////////////////////////////////////
        // DEV / STAGING / LIVE Configuration
        ////////////////////////////////////////////////////////////////////////
        
        define_unless('DEV', !((defined('STAGING') && STAGING) || (defined('LIVE') && LIVE)));
        define_unless('STAGING', !((defined('DEV') && DEV) || (defined('LIVE') && LIVE)));
        define_unless('LIVE', !((defined('DEV') && DEV) || (defined('STAGING') && STAGING)));
        
    }

?>
