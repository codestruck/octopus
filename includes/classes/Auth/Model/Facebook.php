<?php

Octopus::loadClass('Auth_Model');
Octopus::loadClass('Http_Request');

function get_facebook_login_url($path, $scope = 'email') {

    if (!isset($_SESSION['state'])) {
        $_SESSION['state'] = md5(uniqid(rand(), TRUE)); //CSRF protection
    }

    return sprintf('https://www.facebook.com/dialog/oauth?client_id=%s&redirect_uri=%s&scope=%s&state=%s',
        FB_APP_ID,
        rawurlencode( get_full_url($path) ),
        $scope,
        $_SESSION['state']
    );
}

/**
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_Auth_Model_Facebook extends Octopus_Auth_Model {

    private $fb_code;

    function __construct($arg = null) {

        $this->fields = array_merge($this->fields, array(
            'fb_uid',
            'fb_code',
            'fb_access_token',
            'fb_expires',
        ));

        parent::__construct($arg);
    }

    function auth($cache = true) {

        if (parent::auth($cache)) {
            return true;
        }

        $code = get('code');
        if ($code && get('state') == $_SESSION['state']) {

            $this->fb_code = $code;

            if ($this->updateAccessToken()) {
                $this->fbLoginSuccess();
            }

        }

    }

    private function fbLoginSuccess() {

        $this->cleanOutUserAuthTable();

        // Make a record of the login in the user_auth table
        $hash = md5(uniqid(rand(), true));

        $i = new Octopus_DB_Insert();
        $i->comment('Octopus_Auth_Model::login');
        $i->table('user_auth');
        $i->set('user_id', $this->id);
        $i->set('auth_hash', $hash);
        $i->setNow('created');
        $i->setNow('last_activity');
        $i->set('realm', $this->getRealm());
        $i->execute();

        // Set a login cookie
        $this->setAuthHash($hash, true);

        return true;
    }

    private function updateAccessToken() {

        $url = sprintf('https://graph.facebook.com/oauth/access_token?client_id=%s&redirect_uri=%s&client_secret=%s&code=%s',
            FB_APP_ID,
            get_full_urL('/'),
            FB_APP_SECRET,
            $this->fb_code);

        $response = octopus_http_get($url);

        if ($response) {
            parse_str($response, $accessData);


            $url = sprintf('https://graph.facebook.com/me?access_token=%s', $accessData['access_token']);

            $response = octopus_http_get($url);
            $data = json_decode($response);

            $user = $this->_get(array('fb_uid', $data->id));

            if ($user) {
                $this->id = $user->id;
                $this->setData($user);
            }

            $this->fb_access_token = $accessData['access_token'];
            if (isset($accessData['expires'])) {
                $this->fb_expires = $accessData['expires'];
            }

            $this->name = $data->name;
            $this->fb_uid = $data->id;
            if (isset($data->email)) {
                $this->email = $data->email;
            }

            $this->save();

            return true;
        }

        return false;

    }

}
