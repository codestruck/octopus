<?php

class SG_Cookie {

    function get($key) {
        return isset($_COOKIE[$key]) ? $_COOKIE[$key] : false;
    }

    function set($key, $value, $expires = null, $path = null, $domain = null, $secure = false) {

        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            setcookie($key, $value, $expires, $path, $domain, $secure, true);
        } else {
            $_COOKIE[$key] = $value;
        }

    }

    function destroy($key) {
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            setcookie($key, '', time() - 3600, '/');
        } else {
            unset($_COOKIE[$key]);
        }
    }

}

?>
