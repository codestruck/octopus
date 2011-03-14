<?php

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
