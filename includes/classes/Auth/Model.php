<?php

Octopus::loadExternal('phpass');

abstract class Octopus_Auth_Model extends Octopus_Model {

	/**
	 *
	 */
    public $cookieName;

    /**
     * Login realm.
     */
    public $realm = null;

    protected $password_algo_strength = 8;
    protected $portable_passwords = FALSE;
    protected $cookiePath = '/';
    protected $cookieSsl = false;
    protected $rememberDays = 14;

    /**
     * Field(s) that can be used as the 'username' for login purposes. If
     * both are present on the model, both will be checked.
     */
    protected $usernameField = array('email', 'username');

    // By default, the model will check for a field called 'active' or a field
    // called 'hidden'.
    protected $hiddenField = null;
    protected $activeField = null;

    protected $groups = array();

    // Because login() changes the ID of the model, we store auth hashes
    // per-class and per-id
    private static $authHashes = array();

    /**
     * Validates that the user is currently logged in and that their record
     * has not been deleted or deactivated.
     */
    public function auth() {

        $auth = $this->getAuthRecord();

        if (!$auth) {
        	return false;
        }

        $pass = false;

        if ($this->validateAuthRecord($auth)) {
        	$pass = $this->recordStillExistsAndIsActive($auth);
        }

        if (!$pass) {

            $d = new Octopus_DB_Delete();
            $d->comment('Octopus_Auth_Model::auth');
            $d->table('user_auth');
            $d->where('auth_hash = ?', $auth['auth_hash']);
            $d->where('realm = ?', $this->getRealm());
            $d->execute();

			return false;
        }

        // User is auth'd

        if (!$this->id) {

        	// This is a new user record, being re-initialized from a cookie.
        	// So, load the user's data.

        	$user = $this->_get($auth['user_id']);

	        if (!$user) {
	        	throw new Octopus_Exception("User record disappeared!");
	        }

	        $this->id = $user->id;
	        $this->setData($user);
	    }


        $this->afterAuth();

        $this->updateAuthRecord($auth['auth_hash']);

        $this->onAccess();

        return true;
    }

    protected function afterAuth() { }
    protected function onAccess() { }

    /**
     * Changes the password for this user. Autosaves previously saved records.
     * @param $password String New password
     * @param $save Mixed Whether to save the record after changing the password
     * If null, the record will be saved if it has previously been saved (it
	 * has an id).
     */
    public function changePassword($password, $save = null) {

        $checker = new PasswordHash($this->password_algo_strength, $this->portable_passwords);

        $this->password = $checker->HashPassword($password);
        $this->clearPasswordSalt();

        if ($save === null) $save = $this->id;

        if ($save) {
        	return $this->save();
        }

        return true;
    }

    /**
     * Checks the given username/password combination.
     * @return bool Whether the combo is valid.
     */
    public function checkLogin($username, $password) {
    	return !!$this->getUserForLogin($username, $password);
    }

    /**
     * Attempts to log in the given user.
     * If this function succeeds, the contents of $this will be replaced with
     * the data from the logged-in user.
     * @param $username String username or email address
     * @param $password String
     */
    public function login($username, $password, $remember = false) {

    	if (!trim($username)) {
    		// Don't allow logins w/ blank user names
    		return false;
    	}

    	$this->cleanOutUserAuthTable();

    	$user = $this->getUserForLogin($username, $password);
    	if (!$user) {
    		return false;
    	}

    	// The username/pass checks out!

    	// Swap the logged in user in for whatever the previous data was
    	$this->id = $user->id;
    	$this->setData($user);
    	$this->resetDirtyState();

    	// Make a record of the login in the user_auth table
       	$hash = md5(uniqid(rand(), true));
        $ip = get_user_ip();

        $i = new Octopus_DB_Insert();
        $i->comment('Octopus_Auth_Model::login');
        $i->table('user_auth');
        $i->set('user_id', $user->id);
        $i->set('auth_hash', $hash);
        $i->setNow('created');
        $i->setNow('last_activity');
        $i->set('realm', $this->getRealm());
        $i->execute();

        // Set a login cookie
        $this->setAuthHash($hash, $remember);

        return true;
    }

    public function logout() {

    	$hash = $this->getAuthHash();
    	$this->setAuthHash(null);

        if ($hash) {

	        $d = new Octopus_DB_Delete();
	        $d->comment('Octopus_Auth_Model::logout');
	        $d->table('user_auth');
	        $d->where('auth_hash = ?', $hash);
	        $d->where('realm = ?', $this->getRealm());
	        $d->execute();

	    }
    }

    /**
     * Changes the user's password and emails them the new one.
     */
    public function resetPassword($save = null, $sendEmail = true) {

        $password = make_password(8);
        $this->setPassword($password, $save);

        if (!$sendEmail || !$this->email) {
        	return true;
        }

        // TODO: Use an email templating system

	    $settings = Octopus_Settings::singleton();
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

        return true;
    }


    protected function getActiveField() {

        // Support the $activeField instance variable
        if (isset($this->activeField)) {
            return $this->activeField;
        }

        $f = $this->getField('active');
        if ($f) return ($this->activeField = $f->getFieldName());

        return false;
    }

    protected function getHiddenField() {

        // Support the $hiddenField instance variable
        if (isset($this->hiddenField)) {
            return $this->hiddenField;
        }

        $f = $this->getField('hidden');
        if ($f) return ($this->hiddenField = $f->getFieldName());

        return false;
    }

    protected function getRealm() {
    	return $this->realm ? $this->realm : get_class($this);
    }

    /**
     * Adds a filter to the given criteria array that will make it only match
     * users that are currently active (or inactive, depending on what
	 * $active is set to).
     */
    private function addActiveFilter(&$criteria, $active = true) {

    	$activeField = $this->getActiveField();

    	if ($activeField) {
    		$criteria[$activeField] = $active ? 1 : 0;
    		return;
    	}

    	$hiddenField = $this->getHiddenField();

    	if ($hiddenField) {
    		$criteria[$hiddenField] = $active ? 0 : 1;
    		return;
    	}

    	throw new Octopus_Exception("Auth model " . get_class($this) . " does not have a 'hidden' or 'active' field.");

    }

    private function addUserNameFilter(&$criteria, $username) {

    	$candidates = $this->usernameField;
    	if (!$candidates) {
    		throw new Octopus_Exception("User name field not configured for auth model " . get_class($this));
    	}

    	if (!is_array($candidates)) $candidates = array($candidates);

    	$items = array();

    	foreach($candidates as $f) {

    		$f = $this->getField($f);
    		if (!$f) continue;

    		if (!empty($items)) $items[] = 'OR';

    		$items[] = array($f->getFieldName() => $username);
    	}

    	if (empty($items)) {
    		throw new Octopus_Exception("No valid user name field was found on auth model " . get_class($this));
    	}

    	$criteria[] = $items;

    }

    /**
     * Clears out old records in the user_auth table.
     */
    private function cleanOutUserAuthTable() {

        $d = new Octopus_DB_Delete();
        $d->comment('Octopus_Auth_Model::cleanup');
        $d->table('user_auth');
        $d->where('created < DATE_SUB(NOW(), INTERVAL ' . $this->rememberDays . ' DAY)');
        $d->where('realm = ?', $this->getRealm());
        $d->execute();

    }

    private function clearPasswordSalt() {

    	$field = $this->getField('password_salt');
    	if ($field) $this->password_salt = '';

    }

    /**
     * @return String The random hash that identifies this user's record
     * in the user_auth table (if any).
     */
    private function getAuthHash() {

    	$h =& self::$authHashes;
    	$realm = $this->getRealm();

    	if ($this->id && isset($h[$realm]) && isset($h[$realm][$this->id])) {
    		return $h[$realm][$this->id];
    	}

    	if ($this->cookieName) {

    		$hash = Octopus_Cookie::get($this->cookieName);
    		if ($hash) return $hash;

    	}

        if (empty($this->cookieName)) {
            throw new Octopus_Exception("Cookie name has not been configured on auth class " . get_class($this));
        }

        return null;
    }

    private function setAuthHash($hash, $remember = false) {

    	if (empty($this->cookieName)) {
    		throw new Octopus_Exception("Cookie name has not been configured on auth class " . get_class($this));
    	}

		$h =& self::$authHashes;
    	$realm = $this->getRealm();

    	if ($hash) {

	    	if ($this->id) {

	    		if (!isset($h[$realm])) {
	    			$h[$realm] = array();
	    		}

	    		$h[$realm][$this->id] = $hash;

	    	}

	        $expire = $remember ? time() + ($this->rememberDays * 24 * 60 * 60) : 0;
	       	Octopus_Cookie::set($this->cookieName, $hash, $expire, $this->cookiePath, null, $this->cookieSsl);

	    } else {

    		if ($this->cookieName) {
	    		Octopus_Cookie::destroy($this->cookieName);
	    	}

	    	if ($this->id && isset($h[$realm])) {
	    		unset($h[$realm][$this->id]);
	    	}

    	}

    }

    /**
     * @return Mixed The user's record in the auth table or false if there
     * is no record.
     */
    private function getAuthRecord() {

        $hash = $this->getAuthHash();

        if (!$hash) {
        	// No login cookie exists
        	return false;
        }

        $s = new Octopus_DB_Select();
        $s->comment('Octopus_Auth_Model::auth');
        $s->table('user_auth');
        $s->where('auth_hash = ?', $hash);
        $s->where('realm = ?', $this->getRealm());

        $result = $s->fetchRow();

        return $result ? $result : false;
    }

    private function getLogger() {
    	$dir = get_option('LOG_DIR');
    	if (!$dir) $dir = get_option('OCTOPUS_PRIVATE_DIR');
        $log = new Octopus_Logger_File($dir . 'auth.log');
    }

    private function getPasswordSalt() {
    	$field = $this->getField('password_salt');
    	return $field ? $this->password_salt : '';
    }

    /**
     * @return The user model for the given user/pass combo, or false if
     * the combo is invalid.
     */
    private function getUserForLogin($username, $password) {

    	$checker = new PasswordHash($this->password_algo_strength, $this->portable_passwords);

    	$criteria = array();
    	$this->addUserNameFilter($criteria, $username);
    	$this->addActiveFilter($criteria);

    	$user = $this->_get($criteria);

    	if ($user) {

            if (strlen($user->password) == 40) {

                // handle old sha1 based logins

                $oldHash = sha1($user->getPasswordSalt() . $password);
                if ($user->password == $oldHash) {
                    // update password to new style
                    $user->changePassword($password);
                    return $user;
                }

            } else {

                $valid = $checker->CheckPassword($password, $user->password);
                if ($valid) {
                    return $user;
                }

            }

    	} else {

            // take just as long when the username doesn't exist
            $fake_hash = '$P$BBxLPQY.19uT3gfbn66ik2Lv.lA5Rc.';
            $checker->CheckPassword('foobar', $fake_hash);

            return false;
    	}

    }

    private function recordStillExistsAndIsActive($auth) {

    	$criteria = array(
	    	$this->getPrimaryKey() => $auth['user_id'],
	    );
	    $this->addActiveFilter($criteria);

    	$s = new Octopus_DB_Select();
    	$s->comment(__METHOD__);
    	$s->table($this->getTableName(), array($this->getPrimaryKey()));

    	foreach($criteria as $key => $value) {
    		$s->where("$key = ?", $value);
    	}

    	return !!$s->getOne();
    }

    /**
     * Once the user has been auth'd, this makes a note of their last acccess.
     */
    private function updateAuthRecord($hash) {

    	$ip = get_user_ip();
        $hostname = gethostbyaddr($ip);

        $u = new Octopus_DB_Update();
        $u->comment('Octopus_Auth_Model::auth');
        $u->table('user_auth');
        $u->setNow('last_activity');
        $u->set('auth_address', $ip);
        $u->set('auth_hostname', $hostname);

        // TODO: Should this clear the user agent if it is not set?
	    if (isset($_SERVER['HTTP_USER_AGENT'])) {
	        $u->set('user_agent', $_SERVER['HTTP_USER_AGENT']);
	    }

        $u->where('auth_hash = ?', $hash);
        $u->where('realm = ?', $this->getRealm());

        $u->execute();
    }

    /**
     * Given a row from the auth table, examines it to make sure it's still
     * ok.
     */
    protected function validateAuthRecord($auth) {

    	$pass = true;
    	$log = $this->getLogger();

        if (empty($auth['user_id']) || $auth['user_id'] < 1) {
            $pass = false;
            $log->log('Auth Fail: Bad User Id: ' . $auth['user_id']);
        }

        if ($this->id > 0 && intval($this->id) !== intval($auth['user_id'])) {
        	return false;
        }

        $rememberSeconds = ($this->rememberDays * 24 * 60 * 60);

        $created = strtotime($auth['created']);
        if ($created < time() - $rememberSeconds) {
            $pass = false;
            $log->log('Auth Fail: Hash older than remember timeout weeks (' . $rememberSeconds . ' seconds)');
        }

        $ip = get_user_ip();
        $agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : false;

        $ipChanged = false;
        if (!empty($auth['auth_address']) && $auth['auth_address'] != $ip) {
            $ipChanged = true;
        }

        $agentChanged = false;
        if ($agent && !empty($auth['user_agent'])) {
            $agentChanged = $auth['user_agent'] != $agent;
        }

        if ($ipChanged && $agentChanged) {
            $pass = false;
            $log->log('Auth Fail: IP and User Agent changed');
        } else if ($ipChanged) {
            $log->log("Notice: just IP changed {$auth['auth_address']} to {$ip}");
        } else if ($agentChanged) {
            $log->log("Notice: just User Agent changed from '{$auth['user_agent']}' to '{$agent}'");
        }

        return $pass;
    }

}

?>
