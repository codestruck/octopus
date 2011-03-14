<?php

    /*
     * All core app functionality comes from here. Any page served up by the
     * app should include this file first.
     */

    $GLOBALS['ROOT_DIR'] = dirname(dirname(__FILE__)) . '/';
    $GLOBALS['INCLUDES_DIR'] = dirname(__FILE__) . '/';
    $GLOBALS['FUNCTIONS_DIR'] = $GLOBALS['INCLUDES_DIR'] . 'functions/';
    $GLOBALS['CLASSES_DIR'] = $GLOBALS['INCLUDES_DIR'] . 'classes/';
    $GLOBALS['THEMES_DIR'] = $GLOBALS['ROOT_DIR'] . 'themes/';
    $GLOBALS['SITE_DIR'] = $GLOBALS['ROOT_DIR'] . 'site/'; 
    $GLOBALS['SITE_THEMES_DIR'] = $GLOBALS['SITE_DIR'] . 'themes/';
         
    /**
     * Spins up a new instance of the application.
     *
     * @param $options array Bootstrapping options for the app.
     */
    function bootstrap($options = null) {

        global $URL_BASE;
        global $ROOT_DIR, $INCLUDES_DIR, $FUNCTIONS_DIR, $CLASSES_DIR, $THEMES_DIR;
        global $SITE_DIR, $SITE_THEMES_DIR;
        global $NAV;
        
        $defaults = array(
            
            'ROOT_DIR' =>           $ROOT_DIR,
            'INCLUDES_DIR' =>       $INCLUDES_DIR,
            'FUNCTIONS_DIR' =>      $FUNCTIONS_DIR,
            'CLASSES_DIR' =>        $CLASSES_DIR,
            'THEMES_DIR' =>         $THEMES_DIR,
            'SITE_DIR' =>           $SITE_DIR,
            'SITE_THEMES_DIR' =>    $SITE_THEMES_DIR,
            
            /**
             * Whether or not to load site-specific configuration files. 
             */
            'use_site_config' =>    true
        );
        $options = $options ? array_merge($defaults, $options) : $defaults;
        
        ////////////////////////////////////////////////////////////////////////
        // Directory Configuration
        ////////////////////////////////////////////////////////////////////////
        
        define('ROOT_DIR', $ROOT_DIR = $options['ROOT_DIR']);
        define('INCLUDES_DIR', $INCLUDES_DIR = $options['INCLUDES_DIR']);
        define('FUNCTIONS_DIR', $FUNCTIONS_DIR = $options['FUNCTIONS_DIR']);
        define('CLASSES_DIR', $CLASSES_DIR = $options['CLASSES_DIR']);
        define('THEMES_DIR', $THEMES_DIR = $options['THEMES_DIR']);
        define('SITE_DIR', $SITE_DIR = $options['SITE_DIR']);
        define('SITE_THEMES_DIR', $SITE_THEMES_DIR = $options['SITE_THEMES_DIR']);
        
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
        
        $URL_BASE = find_url_base();
        
        if ($URL_BASE === false) {
            error_log('Could not determine URL_BASE. Assuming "/".');
            $URL_BASE = '/';
        }
        
        define('URL_BASE', $URL_BASE);
        
        ////////////////////////////////////////////////////////////////////////
        // Nav Setup
        ////////////////////////////////////////////////////////////////////////
        
        $NAV = new StdClass();
        
        // Add some default routes
        // $NAV->add('/', 'home.php');
        
        ////////////////////////////////////////////////////////////////////////
        // Site-Specific Configuration
        ////////////////////////////////////////////////////////////////////////
        
        if ($options['use_site_config']) {
            
            $configFile = SITE_DIR . 'config.php';
            
            $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : trim(`hostname`);
            $hostConfigFile = SITE_DIR . "config.$host.php";
            
            if (!file_exists($configFile)) {
                // TODO Friendlier message
                echo "No config file found.";
                exit();
            }
            
            if (file_exists($hostConfigFile)) {
                require_once($hostConfigFile);
            }
            
            require_once($configFile);
            
            // Nav Structure
            $navFile = SITE_DIR . 'nav.php';
            if (!file_exists($navFile)) {
                error_log("No nav.php found.");
            } else {
                require_once($navFile);
            }
        }
        
        ////////////////////////////////////////////////////////////////////////
        // DEV / STAGING / LIVE Configuration
        ////////////////////////////////////////////////////////////////////////
        
        define_unless('DEV', !((defined('STAGING') && STAGING) || (defined('LIVE') && LIVE)));
        define_unless('STAGING', !((defined('DEV') && DEV) || (defined('LIVE') && LIVE)));
        define_unless('LIVE', !((defined('DEV') && DEV) || (defined('STAGING') && STAGING)));
        
    }

?>
