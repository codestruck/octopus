<?php

    /**
     * Assembles a URL, ensuring it is properly prefixed etc.
     * @return A nice URL.
     */
    function u($url) {
        
        if (!defined('URL_BASE')) {
            return $url;
        }
        
        if (preg_match('#^[a-z]+://#i', $url)) {
            return $url;
        }
        
        if (strncmp($url, '/', 1) == 0) {

            // It's an absolute path, so prepend URL_BASE 
            
            if (!defined('URL_BASE_ENDING_SLASH')) {
                define('URL_BASE_ENDING_SLASH', strlen(URL_BASE) > 0 && substr(URL_BASE, -1, 1) == '/');
            }
        
            if (URL_BASE_ENDING_SLASH) {
                $url = ltrim($url, '/');
            }
            
            return URL_BASE . $url;
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
     * Does a 301 redirect.
     */
    function moved_permanently($newLocation) {
        
        if (should_redirect() {
                header('HTTP/1.1 301 Moved Permanently');
                header('Location: ' . $newLocation);
        }
        
        exit();
    }
    
    /**
     * Does a temporary redirect.
     */
    function redirect($newLocation) {
        
        $newLocation = u($newLocation);
        if (defined('URL_BASE')) {
            
        if (strncasecmp
        
        if (defined('URL_BASE') && strncasecmp($newLocation, 'http', 4) != 0) {
            $newLocation = URL_BASE . ltrim($newLocation, '/'); 
        }
        
        if (should_redirect()) {
            header('HTTP/1.1 302 Found');
            header('Location: ' . $newLocation);
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
