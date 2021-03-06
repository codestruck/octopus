<?php

    define('OCTOPUS_FLASH_SESSION_KEY', '__octopus_flash__');

    /**
     * @param $name String The name of a define or option.
     * @param $default Mixed Default value if it is not set.
     * @param $options Mixed Array of local options that should override global ones.
     * @return Mixed If a constant is defined named $name, returns that. Otherwise checks w/
     * the running app instance.
     * @copyright (c) 2012 Codestruck, LLC.
     * @license http://opensource.org/licenses/mit-license.php/
     */
    function get_option($name, $default = null, $options = null) {

        if (is_array($name)) {
            $result = array();
            foreach($name as $n) {
                $result[$n] = get_option($n, $default, $options);
            }
            return $result;
        }

        if ($options && isset($options[$name])) {
            return $options[$name];
        } else if (class_exists('Octopus_App') && Octopus_App::isStarted()) {
            $app = Octopus_App::singleton();
            $val = $app->getOption($name, $default);
            if ($val) {
                return $val;
            }
        }

        if (defined($name)) {
            return constant($name);
        } else {
            return $default;
        }

    }

    /**
     * Tries to provide some sort of default site name. By default, the
     * site.name setting uses this if it not set in the site's settings.yaml
     * file.
     * @copyright (c) 2012 Codestruck, LLC.
     * @license http://opensource.org/licenses/mit-license.php/
     */
    function guess_site_name() {

        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
        if (!$host) return '';

        $parts = explode('.', $host);
        $count = count($parts);

        $name = '';

        if ($count > 1) {
            $name = $parts[$count - 2];
        } else {
            $name = $parts[0];
        }

        $name = str_replace('-', ' ', $name);

        return humanize($name);

    }

    /**
     * @copyright (c) 2012 Codestruck, LLC.
     * @license http://opensource.org/licenses/mit-license.php/
     */
    function octopus_api_key($scope = '') {

        $sessionKey = 'OCTOPUS_API_KEY';
        if ($scope) $sessionKey .= '_' . strtoupper($scope);

        if (isset($_SESSION[$sessionKey])) {
            return $_SESSION[$sessionKey];
        }

        return ($_SESSION[$sessionKey] = md5(uniqid()));
    }

    if (!function_exists('define_unless')) {

        /**
         * Define something only if it's not already
         * @copyright (c) 2012 Codestruck, LLC.
         * @license http://opensource.org/licenses/mit-license.php/
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
     * @see Octopus_Debug::isCommandLineEnvironment
     * @copyright (c) 2012 Codestruck, LLC.
     * @license http://opensource.org/licenses/mit-license.php/
     */
    function is_command_line() {
        return Octopus_Debug::isCommandLineEnvironment();
    }

    /**
     * Helper for reading $_GET.
     * @return mixed The value of $_GET[$arg] if present, $default otherwise,
     * or, if called w/o args, whether or not there's anything in $_GET.
     * @copyright (c) 2012 Codestruck, LLC.
     * @license http://opensource.org/licenses/mit-license.php/
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
     * @copyright (c) 2012 Codestruck, LLC.
     * @license http://opensource.org/licenses/mit-license.php/
     */
    function get_numeric($arg) {
        if (isset($_GET[$arg])) {
            $value = $_GET[$arg];
            if (is_numeric($value)) return $value;
        }
        return false;
    }

    /**
     * @copyright (c) 2012 Codestruck, LLC.
     * @license http://opensource.org/licenses/mit-license.php/
     */
    function get_or_post($arg, $default = null) {
        if (isset($_POST[$arg])) {
            return $_POST[$arg];
        } else if (isset($_GET[$arg])) {
            return $_GET[$arg];
        } else {
            return $default;
        }
    }

    /**
     * @copyright (c) 2012 Codestruck, LLC.
     * @license http://opensource.org/licenses/mit-license.php/
     */
    function get_or_post_numeric($arg) {
        $value = get_or_post($arg, null);
        if (is_numeric($value)) return $value;
        return false;
    }

    /**
     * @return bool Whether $arr is an associative array.
     * @copyright (c) 2012 Codestruck, LLC.
     * @license http://opensource.org/licenses/mit-license.php/
     */
    function is_associative_array ($arr) {

        if (!is_array($arr)) {
            return false;
        }

        foreach($arr as $key => $value) {

            if (!is_numeric($key)) {
                return true;
            }

        }

        return false;
    }

    /**
     * @return true if $method is callable and public on $obj.
     * @copyright (c) 2012 Codestruck, LLC.
     * @license http://opensource.org/licenses/mit-license.php/
     */
    function is_callable_and_public($obj, $method) {
        return is_callable(array($obj, $method));
    }

    /**
     * @return bool Whether or not we are currently running in a dev environment,
     * e.g. on a dev's computer.
     *
     * The parameters for this function are there to support testing.
     * @param $live bool Flag for whether we are in a live environment
     * @param $staging bool Flag for whether we are in a staging environment.
     * @param $use_defines bool Whether or not to use defines.
     * @copyright (c) 2012 Codestruck, LLC.
     * @license http://opensource.org/licenses/mit-license.php/
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

        return true;
    }

    /**
     * @return bool Whether or not we are currently running in a production
     * environment.
     *
     * The parameters for this function are there to support testing.
     * @param $dev bool Flag for whether we are in a dev environment
     * @param $staging bool Flag for whether we are in a staging environment.
     * @param $use_defines bool Whether or not to use environment defines.
     * @copyright (c) 2012 Codestruck, LLC.
     * @license http://opensource.org/licenses/mit-license.php/
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
     * @copyright (c) 2012 Codestruck, LLC.
     * @license http://opensource.org/licenses/mit-license.php/
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
            if (preg_match('/^dev\./i', $hostname)) {
                return true;
            }
        }

        // TODO Read path from $_SERVER

        // Also, path
        if ($path !== null) {
            return preg_match('#^/?dev(/|$)#i', $path) ? true : false;
        }

        return false;
    }

    /**
     * Helper for reading $_POST.
     * @return mixed The value of $_POST[$arg] if present, $default otherwise,
     * or, if called w/o args, whether or not there's anything in $_POST.
     * @copyright (c) 2012 Codestruck, LLC.
     * @license http://opensource.org/licenses/mit-license.php/
     */
    function post($arg = null, $default = null) {

        if ($arg === null && $default === null) {
            return count($_POST);
        }

        return isset($_POST[$arg]) ? $_POST[$arg] : $default;
    }

    /**
     * Sets a flash message.
     * @copyright (c) 2012 Codestruck, LLC.
     * @license http://opensource.org/licenses/mit-license.php/
     */
    function set_flash($content, $type = 'success', $options = null) {

        if ($options === null && is_array($type)) {
            $options = $type;
            $type = 'success';
        }

        if (isset($options['type'])) {
            $type = $options['type'];
            unset($options['type']);
        }

        if (empty($_SESSION[OCTOPUS_FLASH_SESSION_KEY])) {
            $_SESSION[OCTOPUS_FLASH_SESSION_KEY] = array();
        }

        if (!$options) $options = array();

        // Don't auto-hide error messages by default
        if ($type == 'error' && !isset($options['persist'])) {
            $options['persist'] = true;
        }

        $_SESSION[OCTOPUS_FLASH_SESSION_KEY][$type] = array(
            'content' => $content,
            'options' => $options
        );
    }

    /**
     * Removes any flash messages set.
     * @copyright (c) 2012 Codestruck, LLC.
     * @license http://opensource.org/licenses/mit-license.php/
     */
    function clear_flash($type = null) {

        if ($type === null) {
            unset($_SESSION[OCTOPUS_FLASH_SESSION_KEY]);
        } else if (isset($_SESSION[OCTOPUS_FLASH_SESSION_KEY])) {
            unset($_SESSION[OCTOPUS_FLASH_SESSION_KEY][$type]);
        }

    }

    /**
     * @copyright (c) 2012 Codestruck, LLC.
     * @license http://opensource.org/licenses/mit-license.php/
     */
    function get_flash($type = 'success', $clear = true) {

        if (is_bool($type)) {
            $clear = $type;
            $type = 'success';
        }

        if (!isset($_SESSION[OCTOPUS_FLASH_SESSION_KEY])) {
            return '';
        }

        if (isset($_SESSION[OCTOPUS_FLASH_SESSION_KEY][$type])) {
            $result = $_SESSION[OCTOPUS_FLASH_SESSION_KEY][$type];
        } else {
            $result = '';
        }

        if ($clear) {
            clear_flash($type);
        }

        return $result;
    }

    /**
     * @copyright (c) 2012 Codestruck, LLC.
     * @license http://opensource.org/licenses/mit-license.php/
     */
    function render_flash($type = null, $clear = true) {

        if (empty($_SESSION[OCTOPUS_FLASH_SESSION_KEY])) {
            return false;
        }

        if ($type === null) {

            // By default, render all flash messages

            $rendered = false;
            foreach($_SESSION[OCTOPUS_FLASH_SESSION_KEY] as $type => $rawContent) {
                $rendered = render_flash($type, $clear) || $rendered;
            }

            return $rendered;
        }

        $flash = get_flash($type, $clear);

        if (empty($flash)) {
            return false;
        }

        $class = array(to_css_class($type) => true);
        $title = null;
        $content = null;

        if (!empty($flash['options'])) {

            foreach($flash['options'] as $key => $value) {
                if ($value) {
                    $class[$key] = true;
                }
            }
        }
        $class = implode(' ', array_keys($class));

        $rawContent = $flash['content'];

        // Allow specifying a separate title/content
        if (is_array($rawContent)) {

            if (isset($rawContent['title'])) {
                $title = $rawContent['title'];
                unset($rawContent['title']);
            }

            if (isset($rawContent['content'])) {
                $content = $rawContent['content'];
                unset($rawContent['content']);
            }

            if ($rawContent) {
                $title = array_shift($rawContent);

               if ($rawContent) {
                   $content = array_shift($rawContent);
               }
            }

        } else {
            $content = $rawContent;
        }

        if (!($content || $title)) {
            return false;
        }

        $titleHtml = $contentHtml = '';
        if ($title) $titleHtml = '            <h3 class="flashTitle">' . $title . '</h3>';
        if ($content) $contentHtml = '            <div class="flashContent">' . $content . '</div>';

        echo <<<END
        <div class="flash $class">
$titleHtml
$contentHtml
        </div>
END;

    }

    /**
     * @copyright (c) 2012 Codestruck, LLC.
     * @license http://opensource.org/licenses/mit-license.php/
     */
    function make_csv($results, $fields) {

        $rows = array();
        $rows[] = $fields;

        foreach ($results as $item) {
            $row = array();

            foreach ($fields as $f) {
                $row[$f] = $item[$f];
            }
            $rows[] = $row;
        }

        $str = '';
        foreach ($rows as $row) {
            $str .= '"' . implode('","', $row) . "\"\n";
        }

        return $str;
    }

