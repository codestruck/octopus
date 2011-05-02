<?php

    if (!function_exists('define_unless')) {

        /**
         * Define something only if it's not already
         */
        function define_unless($constant, $value) {
            if (!defined($constant)) {
                define($constant, $value);
                return true;
            }
            return false;
        }

    }


    /**
     * Helper for reading $_GET.
     * @return mixed The value of $_GET[$arg] if present, $default otherwise,
     * or, if called w/o args, whether or not there's anything in $_GET.
     */
    function get($arg = null, $default = null) {

        if ($arg === null && $default === null) {
            return count($_GET);
        }

        return isset($_GET[$arg]) ? $_GET[$arg] : $default;
    }

    /**
     * Helper for reading $_GET.
     * @return number Value of the given argument, if present and numeric. Otherwise false.
     */
    function get_numeric($arg) {
        if (isset($_GET[$arg])) {
            $value = $_GET[$arg];
            if (is_numeric($value)) return $value;
        }
        return false;
    }

    /**
     * @return bool Whether $arr is an associative array.
     */
    function is_associative_array ($arr) {

        if (!is_array($arr)) {
            return false;
        }

        $last = null;
        foreach($arr as $key => $value) {

            if (!is_numeric($key)) {
                return true;
            }

        }

        return false;
    }

    /**
     * @return bool Whether or not we are currently running in a dev environment,
     * e.g. on a dev's computer.
     *
     * The parameters for this function are there to support testing.
     * @param $live bool Flag for whether we are in a live environment
     * @param $staging bool Flag for whether we are in a staging environment.
     * @param $use_defines bool Whether or not to use defines.
     */
    function is_dev_environment($live = null, $staging = null, $use_defines = true, $hostname = null) {

        if ($use_defines && defined('DEV')) {
            return DEV ? true : false;
        }

        if ($use_defines && $live === null && defined('LIVE')) {
            $live = LIVE;
        }

        if ($use_defines && $staging === null && defined('STAGING')) {
            $staging = STAGING;
        }

        if ($live || $staging) {
            return false;
        }

        if ($hostname === null && isset($_SERVER['HTTP_HOST'])) {
            $hostname = $_SERVER['HTTP_HOST'];
        }

        if ($hostname) {

            $hostname = strtolower($hostname);

            if (preg_match('/^(www\.)?(.*?)(\..*?)*$/', $hostname, $m)) {

                array_shift($m); // get rid of whole match
                if ($m[0] == '') array_shift($m);

                if (count($m) != 2) {
                    return false;
                }

                $last = array_pop($m);

                if (preg_match('/^\.?(com?|net|org|name|edu|gov|biz|info|mil|mobi|aero|asia|cat|coop|int|jobs|museum|pro|tel|travel|xxx)/', $last)) {
                    return false;
                }

                // TODO country codes
            }
        }

        return !isset($_SERVER['HTTP_USER_AGENT']);
    }

    /**
     * @return bool Whether or not we are currently running in a production
     * environment.
     *
     * The parameters for this function are there to support testing.
     * @param $dev bool Flag for whether we are in a dev environment
     * @param $staging bool Flag for whether we are in a staging environment.
     * @param $use_defines bool Whether or not to use environment defines.
     */
    function is_live_environment($dev = null, $staging = null, $use_defines = true) {

        if ($use_defines && defined('LIVE')) {
            return LIVE ? true : false;
        }

        if ($use_defines && defined('DEV')) {
            $dev = DEV;
        }

        if ($use_defines && defined('STAGING')) {
            $staging = STAGING;
        }

        return !($dev || $staging);
    }

    /**
     * @return bool Whether or not we are currently running in a staging
     * environment, e.g. on a dev.domain.
     *
     * The parameters for this function are there to support testing.
     * @param $dev bool Flag for whether we are in a dev environment
     * @param $live bool Flag for whether we are in a live environment.
     * @param $use_defines bool Whether or not to use environment define.
     */
    function is_staging_environment($dev = null, $live = null, $use_defines = true, $hostname = null, $path = null) {

        if ($use_defines && defined('STAGING') && STAGING) {
            return true;
        }

        if ($use_defines && $dev === null && defined('DEV')) {
            $dev = DEV;
        }

        if ($use_defines && $live === null && defined('LIVE')) {
            $live = LIVE;
        }

        if ($dev || $live) {
            return false;
        }

        // Do some tricks w/ hostname
        if ($hostname === null && isset($_SERVER['HTTP_HOST'])) {
            $hostname = $_SERVER['HTTP_HOST'];
        }

        if ($hostname !== null) {
            return preg_match('/^dev\./i', $hostname) ? true : false;
        }

        // TODO Read path from $_SERVER

        // Also, path
        if ($path !== null) {
            return preg_match('#^/?dev(/|$)#i', $path) ? true : false;
        }

        return true;
    }

    /**
     * Helper for reading $_POST.
     * @return mixed The value of $_POST[$arg] if present, $default otherwise,
     * or, if called w/o args, whether or not there's anything in $_POST.
     */
    function post($arg = null, $default = null) {

        if ($arg === null && $default === null) {
            return count($_POST);
        }

        return isset($_POST[$arg]) ? $_POST[$arg] : $default;
    }

?>
