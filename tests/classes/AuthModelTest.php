<?php

class AuthModelTestUser extends Octopus_Auth_Model {

    public $cookieName = 'auth-model-test-user';

    protected $fields = array(
        'email',
        'password',
        'password_salt',
        'active'
    );

}

class NoPasswordSaltUser extends Octopus_Auth_Model {

    public $cookieName = 'no-password-salt';

    protected $fields = array(
        'email',
        'password',
        'active'
    );

}

class UserNameUser extends Octopus_Auth_Model {

    public $cookieName = 'user-name-user';

    protected $fields = array(
        'username',
        'password',
        'active'
    );

}

class NoCookieDefinedUser extends Octopus_Auth_Model {
    protected $fields = array('username', 'password', 'active');
}

/**
 * @group auth
 */
class AuthModelTest extends PHPUnit_Framework_TestCase
{

    function setUp() {

        Octopus_DB_Schema_Model::makeTable('AuthModelTestUser');
        Octopus_DB_Schema_Model::makeTable('NoPasswordSaltUser');
        Octopus_DB_Schema_Model::makeTable('UserNameUser');
        Octopus_DB_Schema_Model::makeTable('NoCookieDefinedUser');

        $schema = new Octopus_DB_Schema();
        $t = $schema->newTable('user_auth');
        $t->newKey('auth_id', true);
        $t->newPrimaryKey('auth_id');
        $t->newKey('user_id');
        $t->newIndex('INDEX', null, 'user_id');
        $t->newTextSmall('auth_hash');
        $t->newIndex('INDEX', null, 'auth_hash');
        $t->newTextSmall('auth_address');
        $t->newTextSmall('auth_hostname');
        $t->newDateTime('created');
        $t->newTextSmall('realm');
        $t->newDateTime('last_activity');
        $t->newTextLarge('user_agent');
        $t->create();


        $user = new AuthModelTestUser();
        $user->logout();

        $db =& Octopus_DB::singleton();
        $db->query('TRUNCATE auth_model_test_users');
        $db->query('TRUNCATE no_password_salt_users');
        $db->query('TRUNCATE no_cookie_defined_users');
        $db->query('TRUNCATE user_name_users');
        $db->query('TRUNCATE user_auth');

    }

    function testNoUsers() {

        $user = new AuthModelTestUser();

        $this->assertFalse($user->auth(), 'new users are not logged in');
        $this->assertFalse($user->login('mike', 'frank'), 'invalid login doesnt work');;

    }

    function testAuthLoadsRecord() {

        $user = new AuthModelTestUser();
        $user->email = 'test';
        $user->password = sha1('test');
        $user->active = 1;
        $user->save();

        $userID = $user->id;

        $user = new AuthModelTestUser();
        $this->assertTrue($user->login('test', 'test'), 'login succeeds');
        $this->assertEquals($userID, $user->id, 'id set after login');

        $user = new AuthModelTestUser();
        $this->assertTrue($user->auth(), 'auth succeeds after login w/ new user');
        $this->assertEquals($userID, $user->id, 'id updated after auth');
        $this->assertEquals('test', $user->email, 'email updated after auth');

    }

    function testAuthFailsWithDifferentUser() {

        $userA = new AuthModelTestUser();
        $userA->email = 'a';
        $userA->password = sha1('a');
        $userA->active = true;
        $userA->save();

        $userB = new AuthModelTestUser();
        $userB->email = 'b';
        $userB->password = sha1('b');
        $userB->active = true;
        $userB->save();

        $user = new AuthModelTestUser();
        $this->assertTrue($user->login('a', 'a'), 'login succeeds');

        $user = new AuthModelTestUser($userA->id);
        $this->assertTrue($user->auth(), 'auth succeeds w/ correct user');

        $user = new AuthModelTestUser();
        $this->assertTrue($user->auth(), 'auth succeeds w/ new user');

        $user = new AuthModelTestUser($userB->id);
        $this->assertFalse($user->auth(), 'auth fails with wrong existing user');

    }



    function testLoginResetsState() {

        $i = new Octopus_DB_Insert();
        $i->table('auth_model_test_users');
        $i->set('email', 'test@test.com');
        $i->set('password', sha1('test'));
        $i->set('active', 1);
        $i->execute();

        $user = new AuthModelTestUser();
        $user->email = 'someother@email.com';
        $this->assertTrue($user->login('test@test.com', 'test'), 'login succeeds');

        $this->assertEquals(1, $user->id);
        $this->assertEquals('test@test.com', $user->email);
        $this->assertTrue(!!$user->active);

    }

    function testCorrectUserIsLoggedIn() {

        $users = array(
            array('email' => 'joe', 'password' => sha1('test'), 'active' => 1),
            array('email' => 'jane', 'password' => sha1('test'), 'active' => 1)
        );

        foreach($users as $u) {
            $i = new Octopus_DB_Insert();
            $i->table('auth_model_test_users');
            foreach($u as $key => $value) {
                $i->set($key, $value);
            }
            $i->execute();
        }

        $user = new AuthModelTestUser();
        $this->assertTrue($user->login('jane', 'test'), 'login succeeds');
        $this->assertEquals(2, $user->id);
        $this->assertEquals('jane', $user->email);

    }

    function testUser() {

        $i = new Octopus_DB_Insert();
        $i->table('auth_model_test_users');
        $i->set('email', 'mike');
        $i->set('password', sha1('frank'));
        $i->set('active', 1);
        $i->execute();

        $user = new AuthModelTestUser();

        $this->assertFalse($user->auth());

        $this->assertTrue($user->login('mike', 'frank'), 'login succeeds');
        $this->assertTrue($user->auth(), "user is auth'd after login");;

        $user->logout();
        $this->assertFalse($user->auth(), "user is not auth'd after logout");

    }

    function testUserDeleted() {

        $i = new Octopus_DB_Insert();
        $i->table('auth_model_test_users');
        $i->set('email', 'mike');
        $i->set('password', sha1('frank'));
        $i->set('active', 1);
        $i->execute();

        $user = new AuthModelTestUser();

        $this->assertTrue($user->login('mike', 'frank'), 'login succeeds');
        $this->assertTrue($user->auth(), 'auth() succeeds after login');

        $d = new Octopus_DB_Delete();
        $d->table('auth_model_test_users');
        $d->where('email = ?', 'mike');
        $d->execute();

        $this->assertFalse($user->auth(), 'auth() fails after deleting record');
    }

    function testDisabledUserLoginFails() {

        $i = new Octopus_DB_Insert();
        $i->table('auth_model_test_users');
        $i->set('email', 'mike');
        $i->set('password', sha1('frank'));
        $i->set('active', 0);
        $i->execute();

        $user = new AuthModelTestUser();

        $this->assertFalse($user->login('mike', 'frank'), 'login fails');
        $this->assertFalse($user->auth(), 'auth() fails after failed login');

    }

    function testDisablingUserUnauths() {

        $i = new Octopus_DB_Insert();
        $i->table('auth_model_test_users');
        $i->set('email', 'mike');
        $i->set('password', sha1('frank'));
        $i->set('active', 1);
        $i->execute();

        $user = new AuthModelTestUser();

        $this->assertTrue($user->login('mike', 'frank'), 'login succeeds');
        $this->assertTrue($user->auth(), 'auth() succeeds after login');

        $u = new Octopus_DB_Update();
        $u->table('auth_model_test_users');
        $u->set('active', 0);
        $u->where('email = ?', 'mike');
        $u->execute();

        $this->assertFalse($user->auth(), 'auth() fails after disabling');
    }

    function testUserInfo() {

        $email = 'mikejestes@gmail.com';

        $i = new Octopus_DB_Insert();
        $i->table('auth_model_test_users');
        $i->set('password', sha1('frank'));
        $i->set('email', $email);
        $i->set('active', 1);
        $i->execute();

        $user_id = $i->getId();

        $user = new AuthModelTestUser();
        $this->assertTrue($user->login($email, 'frank'), 'login succeeds');

        $this->assertEquals($email, $user->email);
        $this->assertEquals($user_id, $user->id);
        $this->assertTrue(!!$user->active, 'user is active');

    }

    function testChangePassword() {

        $email = 'mikejestes@gmail.com';
        $password = 'frank';
        $new_password = 'john';

        $i = new Octopus_DB_Insert();
        $i->table('auth_model_test_users');
        $i->set('password', sha1($password));
        $i->set('email', $email);
        $i->set('active', 1);
        $i->execute();

        $user_id = $i->getId();

        $user = new AuthModelTestUser();

        $this->assertTrue($user->login($email, $password), 'login succeeds');

        $user->changePassword($new_password);
        $this->assertTrue($user->auth(), "user is auth'd after changePassword");

        $user->logout();
        $this->assertFalse($user->auth(), "auth fails after logout");

        $this->assertFalse($user->login($email, $password), "login fails with old password");
        $this->assertFalse($user->auth(), "auth fails after bad login");

        $this->assertTrue($user->login($email, $new_password), "login succeeds with new password");
        $this->assertTrue($user->auth(), "auth succeeds after login");

    }

    /*
    SoleCMS Specific
    function testPermissionsContributor()
    {
        $email = 'mikejestes@gmail.com';
        $password = 'frank';
        $new_password = 'john';

        $i = new Octopus_DB_Insert();
        $i->table('auth_model_test_users');
        $i->set('password', sha1($password));
        $i->set('email', $email);
        $i->set('group_id', 1);
        $i->execute();

        $user_id = $i->getId();

        $user = new AuthModelTestUser();
        $this->assertFalse($user->isUser());
        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->isDeveloper());

        $user->auth();
        $this->assertFalse($user->isUser());
        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->isDeveloper());

        $user->login($username, $password);
        $this->assertFalse($user->isUser());
        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->isDeveloper());
    }

    function testPermissionsUser()
    {
        $email = 'mikejestes@gmail.com';
        $username = 'mike';
        $password = 'frank';
        $new_password = 'john';

        $i = new Octopus_DB_Insert();
        $i->table('auth_model_test_users');
        $i->set('username', $username);
        $i->set('password', sha1($password));
        $i->set('email', $email);
        $i->set('group_id', 2);
        $i->execute();

        $user_id = $i->getId();

        $user = new AuthModelTestUser();
        $this->assertFalse($user->isUser());
        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->isDeveloper());

        $user->auth();
        $this->assertFalse($user->isUser());
        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->isDeveloper());

        $user->login($username, $password);
        $this->assertTrue($user->isUser());
        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->isDeveloper());
    }

    function testPermissionsAdmin()
    {
        $email = 'mikejestes@gmail.com';
        $username = 'mike';
        $password = 'frank';
        $new_password = 'john';

        $i = new Octopus_DB_Insert();
        $i->table('auth_model_test_users');
        $i->set('username', $username);
        $i->set('password', sha1($password));
        $i->set('email', $email);
        $i->set('group_id', 3);
        $i->execute();

        $user_id = $i->getId();

        $user = new AuthModelTestUser();
        $this->assertFalse($user->isUser());
        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->isDeveloper());

        $user->auth();
        $this->assertFalse($user->isUser());
        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->isDeveloper());

        $user->login($username, $password);
        $this->assertTrue($user->isUser());
        $this->assertTrue($user->isAdmin());
        $this->assertFalse($user->isDeveloper());
    }

    function testPermissionsDeveloper()
    {
        $email = 'mikejestes@gmail.com';
        $username = 'mike';
        $password = 'frank';
        $new_password = 'john';

        $i = new Octopus_DB_Insert();
        $i->table('auth_model_test_users');
        $i->set('username', $username);
        $i->set('password', sha1($password));
        $i->set('email', $email);
        $i->set('group_id', 4);
        $i->execute();

        $user_id = $i->getId();

        $user = new AuthModelTestUser();
        $this->assertFalse($user->isUser());
        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->isDeveloper());

        $user->auth();
        $this->assertFalse($user->isUser());
        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->isDeveloper());

        $user->login($username, $password);
        $this->assertTrue($user->isUser());
        $this->assertTrue($user->isAdmin());
        $this->assertTrue($user->isDeveloper());
    }

    function testSingleton() {

        $i = new Octopus_DB_Insert();
        $i->table('auth_model_test_users');
        $i->set('email', 'mike');
        $i->set('password', sha1('frank'));
        $i->set('active', 1);
        $i->execute();

        $user = AuthModelTestUser::singleton();
        $user->logout();

        $this->assertFalse($user->auth(), 'auth fails after logout');

        $this->assertTrue($user->login('mike', 'frank'), 'login succeeds');
        $this->assertTrue($user->auth(), 'auth succeeds after login');

        $user->logout();
        $this->assertFalse($user->auth(), 'auth fails after logout');
    }
    */

    function testOldShaNoSalt() {

        $i = new Octopus_DB_Insert();
        $i->table('auth_model_test_users');
        $i->set('email', 'mike');
        $i->set('password', sha1('frank'));
        $i->set('active', 1);
        $i->execute();

        $user = new AuthModelTestUser();

        $this->assertTrue($user->login('mike', 'frank'), 'login succeeds');
        $this->assertTrue($user->auth(), 'auth succeeds after login');

    }

    function testOldShaWithSalt() {

        $salt = 'aoeu098234pnthaoe0u9834spthao.u08';

        $i = new Octopus_DB_Insert();
        $i->table('auth_model_test_users');
        $i->set('email', 'mike');
        $i->set('password_salt', $salt);
        $i->set('password', sha1($salt . 'frank'));
        $i->set('active', 1);
        $i->execute();

        $user = new AuthModelTestUser();

        $this->assertFalse($user->auth(), "user is not auth'd by default");
        $this->assertTrue($user->login('mike', 'frank'), "login succeeds");
        $this->assertTrue($user->auth(), 'auth succeeds after login');

        $this->assertFalse(!!$user->password_salt, 'salt is reset to nothing after login');
    }

    function testNewStyle() {

        $checker = new PasswordHash(8, TRUE);
        $hash = $checker->HashPassword('frank');

        $i = new Octopus_DB_Insert();
        $i->table('auth_model_test_users');
        $i->set('email', 'mike');
        $i->set('password_salt', '');
        $i->set('password', $hash);
        $i->set('active', 1);
        $i->execute();

        $user = new AuthModelTestUser();
        $this->assertFalse($user->auth(), 'auth fails initially');

        $this->assertTrue($user->login('mike', 'frank'), 'login succeeds');
        $this->assertTrue($user->auth(), "user is auth'd after login");

    }

    function testNoPasswordSalt() {

        $user = new NoPasswordSaltUser();
        $user->email = 'bob';
        $user->password = sha1('test');
        $user->active = 1;
        $user->save();

        $user = new NoPasswordSaltUser();
        $this->assertTrue($user->login('bob', 'test'), 'login succeeds');
        $this->assertTrue($user->auth(), "user is auth'd after login");

    }

    function testUserNameSupport() {

        $user = new UserNameUser();
        $user->username = 'bob';
        $user->password = sha1('test');
        $user->active = 1;
        $user->save();

        $user = new UserNameUser();
        $this->assertTrue($user->login('bob', 'test'), 'login succeeds');
        $this->assertTrue($user->auth(), 'auth succeeds');
        $user->logout();
        $this->assertFalse($user->auth(), "user is not auth'd after logout");

    }

    function testBlankUserNameLoginFails() {

        $user = new AuthModelTestUser();
        $user->email = '';
        $user->password = sha1('test');
        $user->active = true;
        $user->save();

        $user = new AuthModelTestUser();
        $this->assertFalse($user->login('', 'test'), 'login fails with blank username');
        $this->assertFalse($user->auth(), "user is not auth'd");

    }

    /**
     * @expectedException Octopus_Exception
     */
    function testNoCookieNameThrowsException() {

        $user = new NoCookieDefinedUser();
        $user->username = 'test';
        $user->password = sha1('test');
        $user->active = 1;
        $user->save();

        $user = new NoCookieDefinedUser();
        $user->login('test', 'test');
    }

}

?>