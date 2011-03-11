<?php

    /*
     * All core app functionality comes from here. Any page served up by the
     * app should include this file first.
     */

    require_once('functions/debug.php');
    require_once('functions/misc.php');
    require_once('functions/strings.php');
    require_once('functions/files.php');
    
    if (!is_file('../site/config.php')) {
        echo "Configuration file not found.";
        exit();
    }
    
    require_once('../site/config.php');
    
    define_unless('DEV', (defined('STAGING') && STAGING) || (defined('LIVE') && LIVE) ? 0 : 1);
    define_unless('STAGING', (defined('DEV') && DEV) || (defined('LIVE') && LIVE) ? 0 : 1);
    define_unless('LIVE', (defined('DEV') && DEV) || (defined('STAGING') && STAGING) ? 0 : 1);

?>
