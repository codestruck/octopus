<?php

    $__SG_CANCEL_REDIRECT__ = false;

    /**
     * Assembles a URL, ensuring it is properly prefixed etc.
     * @param $url string URL to format.
     * @param $args array Any querystring arguments to add to the URL. By
     * default, this is processed as changes to the current querystring. Setting
     * a key to null will remove it from the querystring. To overwrite $url's
     * current querystring, set the 'replace_qs' option in $options.
     * @param $options array Options (mostly for testing).
     * @return A nice URL.
     */
    function u($url, $args = null, $options = null) {

        // Prepend URL_BASE if it's not e.g. an http:// url
        if (!preg_match('#^[a-z0-9]+://#i', $url)) {

            $url_base = defined('URL_BASE') ? URL_BASE : false;

            if ($options && isset($options['URL_BASE'])) {
                $url_base = $options['URL_BASE'];
            }

            if ($url_base) {

                if (strncmp($url, '/', 1) == 0) {
                    // It's an absolute path, so prepend URL_BASE
                    $url = $url_base . ltrim($url, '/');
                }

            }
        }

        if ($args === null) return $url;

        // Process qs changes
        $qPos = strpos($url, '?');
        $qs = '';
        if ($qPos !== false) {
            $qs = substr($url, $qPos + 1);
            $url = substr($url, 0, $qPos);
        }

        if ($options && isset($options['replace_qs']) && $options['replace_qs']) {
            // Replace the current querystring
            $qs = http_build_query($args);
            if ($qs) $url .= '?' . $qs;
            return $url;
        }

        // Apply changes to the querystring.
        $oldArgs = array();
        parse_str($qs, $oldArgs);

        foreach($args as $key => $value) {

            if ($value === null) {
                unset($oldArgs[$key]);
                continue;
            }

            $oldArgs[$key] = $value;
        }

        $qs = http_build_query($oldArgs);

        if ($qs) {
            $url .= '?' . $qs;
        }

        return $url;
    }

    /**
     * Cancels any upcoming redirects.
     */
    function cancel_redirects($cancel = true) {
        global $__SG_CANCEL_REDIRECT__;
        $__SG_CANCEL_REDIRECT__ =  $cancel;
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
         * result = '/'
         *
         * $documentRoot = /var/www/
         * $rootDir = /var/www/subdir/
         * result = '/subdir/'
         *
         * $documentRoot = /var/www/
         * $rootDir = /some/weird/dir/
         * result = false
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
    function make_url($url, $args = null, $options = null) {
        return u($url, $args, $options);
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
        global $__SG_CANCEL_REDIRECT__;
        return $__SG_CANCEL_REDIRECT__;
    }

?>
