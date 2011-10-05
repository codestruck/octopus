<?php

function make_password($length) {
    $chars = "234567890abcdefghijkmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
    $i = 0;
    $password = "";
    while ($i <= $length) {
        $password .= $chars{mt_rand(0,strlen($chars) - 1)};
        $i++;
    }
    return $password;
}

function get_security_timeval() {
    $valid_seconds = 86400;
    return ceil(time() / ($valid_seconds / 2 ));
}

function get_security_token($user_id, $action = 'default_action') {
    $timeval = get_security_timeval();
    return security_hash($timeval . $user_id . $action);
}

function get_security_field($user_id, $action = 'default_action', $field = '__security_token') {
    return sprintf('<input type="hidden" name="%s" value="%s" />', $field, get_security_token($user_id, $action));
}

function get_security_url($url, $user_id, $action = 'default_action', $field = '__security_token') {
    $token = get_security_token($user_id, $action);
    $sep = preg_match('/\?/', $url) ? '&amp;' : '?';
    return sprintf('%s%s%s=%s', $url, $sep, $field, $token);
}

function verify_security_token($token, $user_id, $action = 'defualt_action') {
    $timeval = get_security_timeval();

    if (security_hash($timeval . $user_id . $action) == $token) {
        return 1;
    } else if (security_hash($timeval - 1 . $user_id . $action) == $token) {
        return 2;
    }

    return false;
}

function security_hash($data) {
    $salt = get_salt();
    return hash_hmac('sha1', $data, $salt);
}

function get_salt() {
    $key = '_internal_salt';
    $settings = new Octopus_Settings();
    $salt = $settings->get($key);
    if (!$salt) {
        $salt = make_password(64);
        $settings->set($key, $salt);
    }

    return $salt;
}
