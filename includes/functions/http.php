<?php

    /**
     * Assembles a URL, ensuring it is properly prefixed etc. If the url starts
     * with a '/', the slash is replaced with URL_BASE.
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
        if (!preg_match('#^[a-z0-9-]*://#i', $url)) {

            $url_base = defined('URL_BASE') ? URL_BASE : false;

            if ($options && isset($options['URL_BASE'])) {
                $url_base = $options['URL_BASE'];
            }

            // Detect if URL_BASE has already been applied
            if ($url_base) {

                if (strncasecmp($url, $url_base, strlen($url_base)) != 0) {

                    if (strncmp($url, '/', 1) == 0) {
                        // It's an absolute path, so prepend URL_BASE
                        $url = $url_base . ltrim($url, '/');
                    }

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

        $qs = octopus_http_build_query($oldArgs);

        if ($qs) {
            $url .= '?' . $qs;
        }

        return $url;
    }

    // native version requires 5.4 to properly encode spaces for GET requests
    function octopus_http_build_query($data, $seperator = '&', $method = 'GET') {

        $str = '';
        $encFunc = 'rawurlencode';
        if (strtoupper($method) == 'POST') {
            $encFunc = 'urlencode';
        }

        foreach ($data as $key => $value) {
            if ($value !== null) {
                $str .= $encFunc($key) . '=' . $encFunc($value) . $seperator;
            }
        }
        $str = rtrim($str, $seperator);

        return $str;

    }

    /**
     * Cancels any upcoming redirects.
     */
    function cancel_redirects($cancel = true) {

        $GLOBALS['__OCTOPUS_CANCEL_REDIRECT__'] = $cancel;

        if (defined('DEV') && DEV && class_exists('Octopus_Debug')) {
            $GLOBALS['__OCTOPUS_CANCEL_REDIRECT_BACKTRACE'] = debug_backtrace();
        }


        return $cancel;
    }

    function uncancel_redirects() {
        return cancel_redirects(false);
    }

    /**
     * @return mixed The base path for the site, off which all URLs should be
     * built. If the path can't be determined, returns false.
     * @param $rootDir string ROOT_DIR value to use, defaulting to ROOT_DIR.
     * @param $documentRoot string Document root to use when calculating. Defaults to $_SERVER['DOCUMENT_ROOT']
     */
    function find_url_base($rootDir = null, $documentRoot = null) {

        $rootDir = $rootDir ? $rootDir : ROOT_DIR;

        if (!$documentRoot) {

            if (!empty($_SERVER['DOCUMENT_ROOT'])) {
                $documentRoot = $_SERVER['DOCUMENT_ROOT'];
            } else {
                $documentRoot = $rootDir;
            }

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

        if ($documentRoot) $documentRoot = rtrim($documentRoot, '/') . '/';
        if ($rootDir) $rootDir = rtrim($rootDir, '/') . '/';

        $documentRootLen = strlen($documentRoot);
        $rootDirLen = strlen($rootDir);

        if ($rootDirLen < $documentRootLen) {
            return false;
        }

        if (strncasecmp($rootDir, $documentRoot, $documentRootLen) === 0) {

            $base = substr($rootDir, $documentRootLen);
            if (!$base) return '/';

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
     * Ensure external urls start with protocol (http://)
     */
    function make_external_url($url, $https = null) {

        $start = 'http://';

        if (!trim($url)) {
            return '';
        }

        if ($https) {
            $start = 'https://';
        } elseif ($https === null) {
            // detect and allow either protocol
            if (preg_match('/^(https?:\/\/)/', $url, $matches)) {
                $start = $matches[1];
            }
        }

        return start_in($start, preg_replace('/^https?:\/\//', '', $url));
    }

    /**
     * Does a 301 redirect.
     */
    function moved_permanently($newLocation) {
        redirect($newLocation, true);
    }

    /**
     * Does an HTTP redirect.
     * @return bool FALSE if the redirect is suppressed, otherwise it calls
     * exit() before returning.
     */
    function redirect($newLocation, $permanent = true) {

        $newLocation = u($newLocation);

        if (should_redirect()) {
            header($permanent ? 'HTTP/1.1 301 Moved Permanently' : 'HTTP/1.1 302 Found');
            header('Location: ' . u($newLocation));
        } else {

            notify_of_squashed_redirect($newLocation);
            return false;
        }

        exit();
    }

    function notify_of_squashed_redirect($location, $resp = null) {

        // TODO: log?

        if (defined('DEV') && DEV && class_exists('Octopus_Debug')) {

            $d = new Octopus_Debug();
            $d->addSquashedRedirect($location);

            if (!empty($GLOBALS['__OCTOPUS_CANCEL_REDIRECT_BACKTRACE'])) {
                $d->add('Cancellation Source Backtrace', Octopus_Debug::getBacktraceHtml($GLOBALS['__OCTOPUS_CANCEL_REDIRECT_BACKTRACE']));
            }

            if ($resp) {
                $resp->append($d->render(true));
            } else {
                $d->render();
            }
        }

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

        if (!isset($GLOBALS['__OCTOPUS_CANCEL_REDIRECT__'])) {
            return true;
        }

        return !$GLOBALS['__OCTOPUS_CANCEL_REDIRECT__'];
    }


    /**
     * Redirect based on a session key, with default destination
     */
    function handle_session_redirect($key, $default) {

        $dest = $default;
        if (isset($_SESSION[$key])) {
            $dest = $_SESSION[$key];
            unset($_SESSION[$key]);
        }

        redirect($dest);
    }

    /**
     * Given some HTML, makes sure all the href="..." and
     * src="..." attributes are full URLS.
     */
    function expand_relative_urls($html, $secure = null, $options = array()) {

        $worker = new __expand_relative_urls_worker($secure, $options);

        return preg_replace_callback(
            '/(\s+)(href|src)(\s*=\s*)([\'"])(.*?)\4/i',
            array($worker, 'replaceCallback'),
            $html
        );
    }

    class __expand_relative_urls_worker {
        private $secure, $options;
        public function __construct($secure, $options) {
            $this->secure = $secure;
            $this->options = $options;
        }
        public function replaceCallback($matches) {
            $url = get_full_url($matches[5], $this->secure, $this->options);
            return "{$matches[1]}{$matches[2]}{$matches[3]}{$matches[4]}{$url}{$matches[4]}";
        }
    }

    /**
     * @return String A full external URL to the given path.
     * @param $path String Path in the app.
     * @param $secure Mixed Whether or not to use HTTPS. If null, the current
     * scheme is used.
     * @param $options Array extra options
     */
    function get_full_url($path, $secure = null, $options = array()) {

        if (is_array($secure)) {
            $options = array_merge($secure, $options);
            $secure = null;
        }

        if (!empty($options['secure'])) {
            $secure = true;
        } else if (isset($options['HTTPS'])) {
            $secure = strcasecmp('on', $options['HTTPS']) == 0;
            unset($options['HTTPS']);
        }

        if ($secure === null) {
            $secure = (isset($_SERVER['HTTPS']) && strcasecmp('on', $_SERVER['HTTPS']) === 0);
        }

        $host = '';

        if (isset($options['HTTP_HOST'])) {
            $host = $options['HTTP_HOST'];
        } else if (isset($_SERVER['HTTP_HOST'])) {
            $host = $_SERVER['HTTP_HOST'];
        } else {
            $host = trim(`hostname`);
        }

        if (isset($options['SERVER_PORT'])) {
            $port = $options['SERVER_PORT'];
        } else if (isset($_SERVER['SERVER_PORT'])) {
            $port = $_SERVER['SERVER_PORT'];
        } else {
            $port = $secure ? 443 : 80;
        }

        if ($secure && ($port == 443)) {
            $port = '';
        } else if (!$secure && ($port == 80)) {
            $port = '';
        } else {
            $port = ':' . $port;
        }

        $path = trim($path);

        // Handle being handed in a full url
        if (preg_match('#\s*([a-z0-9_-]*)://([^/]*?)(:(\d+))?/(.*)#i', $path, $m)) {

            $inScheme = $m[1];
            $inServer = $m[2];
            $inPort = $m[4];
            $inPath = $m[5];


            if ($inServer && strcasecmp($inServer, $host) !== 0) {
                // This points to a different machine.
                return $path;
            }

            if ($inPort && intval($port) !== intval($inPort)) {
                // This points to a different port
                return $path;
            }

            $path = $inPath ? '/' . $inPath : '';
        }

        $scheme = $secure ? 'https' : 'http';
        $path = u($path, null, $options);

        if ($path && $path[0] !== '/') {
            throw new Octopus_Exception("Can't turn a relative path into a full url: $path");
        }

        return "{$scheme}://{$host}{$port}{$path}";
    }

    /**
     * @return String The user's IP address.
     */
    function get_user_ip() {

        $ip = '127.0.0.1';

        if (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }

        return $ip;
    }

?>
