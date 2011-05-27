<?php

Octopus::loadExternal('phpass');

Octopus::loadClass('Octopus_Cookie');
Octopus::loadClass('Octopus_DB_Delete');
Octopus::loadClass('Octopus_DB_Insert');
Octopus::loadClass('Octopus_DB_Select');
Octopus::loadClass('Octopus_DB_Update');
Octopus::loadClass('Octopus_Mail');
Octopus::loadClass('Octopus_Settings');
Octopus::loadClass('Octopus_Model');

function createPassword($length) {
    $chars = "234567890abcdefghijkmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
    $i = 0;
    $password = "";
    while ($i <= $length) {
        $password .= $chars{mt_rand(0,strlen($chars) - 1)};
        $i++;
    }
    return $password;
}

abstract class Octopus_Auth_Model extends Octopus_Model {

    public $cookieName;
    public $realm;

    protected $password_algo_strength = 8;
    protected $portable_passwords = FALSE;
    protected $cookiePath = '/';
    protected $cookieSsl = false;
    protected $hiddenField = 'hidden';
    protected $rememberDays = 14;
    protected $rememberSeconds;
    protected $usernameField = 'email';
    protected $primaryKey = 'user_id';
    protected $info = array();

    protected $groups = array();
    protected $last_login;
    protected $total_logins;
    protected $last_host;
    protected $last_ip;

    function __construct($arg = null) {
        $pk = $this->primaryKey;
        $this->$pk = null;
        $this->rememberSeconds = 60*60*24 * $this->rememberDays;
        parent::__construct($arg);
    }

    function afterAuth() {
    }

    function updateLoginFields($u) {
        return null;
    }

    function login($username, $password, $remember = false) {

        $this->cleanup();

        if ($this->checkLogin($username, $password)) {

            $hash = md5(uniqid(rand(), true));
            $ip = $this->getUserAddress();

            $i = new Octopus_DB_Insert();
            $i->table('user_auth');
            $i->set('user_id', $this->info[$this->primaryKey]);
            $i->set('auth_hash', $hash);
            $i->setNow('created');
            $i->setNow('last_activity');
            $i->set('realm', $this->realm);
            $i->execute();

            $expire = 0;

            if ($remember) {
                $expire = time() + $this->rememberSeconds;
            }

            Octopus_Cookie::set($this->cookieName, $hash, $expire, $this->cookiePath, null, $this->cookieSsl);

            $u = new Octopus_DB_Update();
            $u->table($this->table);

            $u = $this->updateLoginFields($u);

            if ($u) {

                $u->where($this->primaryKey . ' = ?', $this->info[$this->primaryKey]);
                $u->where($this->hiddenField . ' = 0');
                $u->execute();
            }

            $this->auth();

            return true;
        }

        return false;
    }

    function cleanup() {

        $d = new Octopus_DB_Delete();
        $d->table('user_auth');
        $d->where('created < DATE_SUB(NOW(), INTERVAL ' . $this->rememberDays . ' DAY)');
        $d->where('realm = ?', $this->realm);
        $d->execute();

    }

    function checkLogin($username, $password) {

        $checker = new PasswordHash($this->password_algo_strength, $this->portable_passwords);

        $s = new Octopus_DB_Select();
        $s->comment('Octopus_Admin_User_Auth::checkLogin');
        $s->table($this->table);
        $s->where($this->usernameField . ' = ?', $username);
        $s->where($this->hiddenField . ' = 0');
        $result = $s->fetchRow();

        if ($result) {

            if (strlen($result['password']) == 40) {
                // handle old sha1 based logins

                $oldHash = sha1($result['password_salt'] . $password);
                if ($result['password'] == $oldHash) {
                    $this->info = $result;

                    // update password to new style
                    $pk = $this->primaryKey;
                    $this->$pk = $result[$this->primaryKey];
                    $this->changePassword($password);
                    $this->$pk = null;

                    return true;
                }
            } else {

                $valid = $checker->CheckPassword($password, $result['password']);
                if ($valid) {
                    $this->info = $result;
                    return true;
                }

            }

        } else {

            // take just as long when the username doesn't exist
            $fake_hash = '$P$BBxLPQY.19uT3gfbn66ik2Lv.lA5Rc.';
            $checker->CheckPassword('foobar', $fake_hash);
        }

        return false;
    }

    function changePassword($password) {

        $pk = $this->primaryKey;
        if ($this->$pk < 1) {
            return false;
        }

        $checker = new PasswordHash($this->password_algo_strength, $this->portable_passwords);
        $hash = $checker->HashPassword($password);

        $s = new Octopus_DB_Select();
        $s->table($this->table);
        $s->limit(1);
        $row = $s->fetchRow();

        $u = new Octopus_DB_Update();
        $u->table($this->table);
        $u->set('password', $hash);

        if (isset($row['password_salt'])) {
            $u->set('password_salt', '');
        }

        $u->where($this->primaryKey . ' = ?', $this->$pk);
        $u->where($this->hiddenField . ' = 0');
        $u->execute();

        return true;
    }

    function resetPassword() {

        $pk = $this->primaryKey;
        if ($this->$pk < 1) {
            return false;
        }

        $password = createPassword(8);

        $checker = new PasswordHash($this->password_algo_strength, $this->portable_passwords);
        $hash = $checker->HashPassword($password);

        $u = new Octopus_DB_Update();
        $u->table($this->table);
        $u->set('password', $hash);
        $u->set('password_salt', '');
        $u->where($this->primaryKey . ' = ?', $this->$pk);
        $u->where($this->hiddenField . ' = 0');
        $u->execute();

        $s = new Octopus_DB_Select();
        $s->table($this->table, array('email'));
        $s->where($this->primaryKey . ' = ?', $this->$pk);
        $email = $s->getOne();

        if ($email) {

            $settings =& Octopus_Settings::singleton();
            $from = $settings->get('info_email');
            $site_name = $settings->get('site_name');

            $subject = 'Password Reset on ' . $site_name;

            $text = <<<END

You requested a password reset.

Here is your new password:

$password

You may change it after logging in to the site.

END;

            $mail = new Octopus_Mail();
            $mail->to($email);
            $mail->from($from);
            $mail->subject($subject);
            $mail->text($text);
            $mail->send();

        }

        return true;
    }

    function logout() {

        $pk = $this->primaryKey;

        $hash = Octopus_Cookie::get($this->cookieName);
        $this->$pk = null;
        Octopus_Cookie::destroy($this->cookieName);

        if (!$hash) {
            return;
        }

        $d = new Octopus_DB_Delete();
        $d->table('user_auth');
        $d->where('auth_hash = ?', $hash);
        $d->where('realm = ?', $this->realm);
        $d->execute();

    }

    function auth() {

        $pk = $this->primaryKey;
        if ($this->$pk > 0) {
            return true;
        }

        $hash = Octopus_Cookie::get($this->cookieName);

        if ($hash) {
            $log = new Octopus_Logger_File(LOG_DIR . 'auth.log');

            $ip = $this->getUserAddress();

            $s = new Octopus_DB_Select();
            $s->comment('Octopus_Auth_Base::auth');
            $s->table('user_auth');
            $s->where('auth_hash = ?', $hash);
            $s->where('realm = ?', $this->realm);

            $authData = $s->fetchRow();
            $user_id = $authData['user_id'];

            $pass = true;

            if ($user_id < 1) {
                $pass = false;
                $log->log('Auth Fail: Bad User Id: ' . $user_id);
            }

            $created = strtotime($authData['created']);
            if ($created < time() - $this->rememberSeconds) {
                $pass = false;
                $log->log('Auth Fail: Hash older than 2 weeks');
            }

            $ipChanged = false;
            if (!empty($authData['auth_address']) && $authData['auth_address'] != $ip) {
                $ipChanged = true;
            }

            $agentChanged = false;
            if (isset($_SERVER['HTTP_USER_AGENT']) && !empty($authData['user_agent'])) {
                if ($authData['user_agent'] != $_SERVER['HTTP_USER_AGENT']) {
                    $agentChanged = true;
                }
            }

            if ($ipChanged && $agentChanged) {
                $pass = false;
                $log->log('Auth Fail: IP and User Agent changed');
            } else if ($ipChanged) {
                $log->log("Notice: just IP changed {$authData['auth_address']} to {$ip}");
            } else if ($agentChanged) {
                $log->log("Notice: just User Agent changed from '{$authData['user_agent']}' to '{$_SERVER['HTTP_USER_AGENT']}'");
            }

            // check if user exists
            $s = new Octopus_DB_Select();
            $s->comment('Octopus_Auth_Base::auth');
            $s->table($this->table);
            $s->where($this->primaryKey . ' = ?', $user_id);
            $result = $s->fetchRow();

            if (!$result) {
                $pass = false;
            }

            if ($pass) {

                // set user Data
                foreach ($result as $k => $v) {
                    $this->$k = $v;
                }

                $this->afterAuth();

                $hostname = gethostbyaddr($ip);

                $u = new Octopus_DB_Update();
                $u->comment('Octopus_Auth_Base::auth');
                $u->table('user_auth');
                $u->setNow('last_activity');
                $u->set('auth_address', $ip);
                $u->set('auth_hostname', $hostname);

                if (isset($_SERVER['HTTP_USER_AGENT'])) {
                    $u->set('user_agent', $_SERVER['HTTP_USER_AGENT']);
                }

                $u->where('auth_hash = ?', $hash);
                $u->where('realm = ?', $this->realm);
                $u->execute();

                return true;

            } else {
                $d = new Octopus_DB_Delete();
                $d->table('user_auth');
                $d->where('auth_hash = ?', $hash);
                $d->where('realm = ?', $this->realm);
                $d->execute();

                return false;
            }

        }

        return false;
    }

    function getUserAddress() {
        $ip = '127.0.0.1';

        if (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }

        return $ip;
    }

    function getApiKey() {

        $_SESSION['api_key'] = substr(sha1('sole' . Octopus_Cookie::get($this->cookieName)), 5, 20);
        return $_SESSION['api_key'];

    }

}

?>
