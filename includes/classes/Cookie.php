<?php

class Octopus_Cookie {

    public static function get($key) {
        return isset($_COOKIE[$key]) ? $_COOKIE[$key] : false;
    }

    public static function set($key, $value, $expires = null, $path = null, $domain = null, $secure = false, $httpOnly = true) {

        if (isset($_SERVER['HTTP_USER_AGENT'])) {

            if (version_compare(phpversion(), '5.2.0', '>=')) {
                // 7th parameter, 'httponly', was added in 5.2
                setcookie($key, $value, $expires, $path, $domain, $secure, $httpOnly);
            } else {
                setcookie($key, $value, $expires, $path, $domain, $secure);
            }

        } else {
            $_COOKIE[$key] = $value;
        }

    }

    public static function destroy($key) {
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            setcookie($key, '', time() - 3600, '/');
        } else {
            unset($_COOKIE[$key]);
        }
    }

}

?>
