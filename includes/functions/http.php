<?php

    /**
     * Assembles a URL, ensuring it is properly prefixed etc.
     * @param $url string URL to format.
     * @param $args array A new querystring for the URL.
     * @param $options array Options (mostly for testing).
     * @return A nice URL.
     */
    function u($url, $args = null, $options = null) {
        
        $url_base = defined('URL_BASE') ? URL_BASE : false;
        if ($options && isset($options['URL_BASE'])) {
            $url_base = $options['URL_BASE'];
        }
        
        if (!$url_base) {
            return $url;
        }

        if (preg_match('#^[a-z]+://#i', $url)) {
            return $url;
        }
        
        if (strncmp($url, '/', 1) == 0) {
            // It's an absolute path, so prepend URL_BASE
            return $url_base . ltrim($url, '/');
        }
        
        // It is a relative path, so keep in tact.
        return $url;
    }

    /**
     * Cancels any upcoming redirects.
     */
    function cancel_redirects($cancel = true) {
        $_SESSION['_sg_cancel_redirect'] = $cancel;
    }
    
    /**
     * @return mixed The base path for the site, off which all URLs should be 
     * built. If the path can't be determined, returns false.
     * @param $rootDir string ROOT_DIR value to use, defaulting to ROOT_DIR.
     * @param $documentRoot string Document root to use when calculating. Defaults to $_SERVER['DOCUMENT_ROOT']
     */
    function find_url_base($rootDir = null, $documentRoot = null) {
        
        $rootDir = $rootDir ? $rootDir : ROOT_DIR;
        $documentRoot = $documentRoot ? $documentRoot : $_SERVER['DOCUMENT_ROOT'];
        
        if (!$documentRoot) {
            return '/'; // probably testing or on command line
        }
        
        /*
         * Typical cases:
         * 
         * $documentRoot = /var/www/
         * $rootDir = /var/www/
         *
         * $documentRoot = /var/www/
         * $rootDir = /var/www/subdir/
         *
         * $documentRoot = /var/www/
         * $rootDir = /some/weird/dir/
         * 
         */
        
        if (strncasecmp($rootDir, $documentRoot, strlen($documentRoot)) == 0) {
            
            
            $base = substr($rootDir, strlen($documentRoot));
            if ($base === false) return '/';
            
            return start_in('/', end_in('/', $base));
            
        } else {
            // Something weird is going on
            return false;
        }
    }
    
    /**
     * Verbose alias for u().
     */
    function make_url($url) {
        return u($url);
    }

    /**
     * Does a 301 redirect.
     */
    function moved_permanently($newLocation) {
        redirect($newLocation, true);
    }
    
    /**
     * Does an HTTP redirect.
     */
    function redirect($newLocation, $permanent = true) {
        
        $newLocation = u($newLocation);
        
        if (should_redirect()) {
            header($permanent ? 'HTTP/1.1 301 Moved Permanently' : 'HTTP/1.1 302 Found');
            header('Location: ' . u($newLocation));
        } else {
            // TODO: log?
        }
        
        exit();
    }
    
    /**
     * Reloads the current page.
     */
    function reload() {
        redirect($_SERVER['REQUEST_URI']);
    }
    
    /**
     * @return bool Whether you should process a redirect.
     */
    function should_redirect() {
        if (!isset($_SESSION['_sg_cancel_redirect'])) {
            return true;
        }
        return $_SESSION['_sg_cancel_redirect'];
    }

?>
