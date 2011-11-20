<?php

class SettingsTest extends Octopus_DB_TestCase {

    public $testDir = '.settings-test';

    function __construct() {
        parent::__construct('settings/basic.xml');
    }

    function setUp() {

        parent::setUp();

        $this->deleteTestDir();
        mkdir($this->testDir);
    }

    function deleteTestDir() {

        `rm -rf {$this->testDir}`;
    }

    function tearDown() {
        parent::tearDown();
        $this->deleteTestDir();
    }

    function createTables(&$db) {

        $db->query("

            CREATE TABLE IF NOT EXISTS settings (

                `name` varchar(100) NOT NULL,
                `value` text,
                PRIMARY KEY (`name`)

            );


        ");

    }

    function dropTables(&$db) {

        //$db->query('DROP TABLE IF EXISTS settings');

    }

    function testReadFromDB() {

        $settings = new Octopus_Settings();

        $this->assertEquals('Project Octopus!', $settings->get('site_name'));
        $this->assertEquals(0.1, $settings->get('site_version'));

    }

    function testWriteToDB() {

        $settings = new Octopus_Settings();
        $settings->set('foo', 'bar');

        $settings = new Octopus_Settings();
        $this->assertEquals('bar', $settings->get('foo'));

    }

    function testReset() {

        $file = $this->testDir . '/' . to_slug(__METHOD__) . '.yaml';

        file_put_contents(
            $file,
            <<<END
name:
  desc: "Your Name"
  type: text
  default: Joe Blow
age:
  desc: "Your Age"
  type: numeric
  default: 20
END
        );

        $settings = new Octopus_Settings();
        $settings->addFromFile($file);

        $settings->set('name', 'Matt');
        $settings  = new Octopus_Settings();

        $this->assertEquals('Matt', $settings->get('name'));

        $settings->reset('name');
        $settings = new Octopus_Settings();
        $settings->addFromFile($file);

        $this->assertEquals('Joe Blow', $settings->get('name'));


    }

    function testBasicYamlDefaults() {

        $file = $this->testDir . '/' . to_slug(__METHOD__) . '.yaml';

        file_put_contents(
            $file,
            <<<END
name:
  desc: "Your Name"
  type: text
  default: Joe Blow
age:
  desc: "Your Age"
  type: numeric
  default: 20
END
        );

        $settings = new Octopus_Settings();
        $settings->addFromFile($file);

        $this->assertEquals(
            'Joe Blow',
            $settings->get('name'),
            'name is wrong'
        );

        $this->assertEquals(
            20,
            $settings->get('age'),
            'age is wrong'
        );

    }

    function testIteration() {

        $settings = new Octopus_Settings();
        $settings->addFromYaml(<<<END
site_lang:
  default: en-us
site_name:
  default: Default Site Name
END
        );

        $expected = array(
            'site_lang' => 'en-us',
            'site_name' => 'Project Octopus!',
            'site_version' => 0.1
        );
        $keys = array_keys($expected);
        $values = array_values($expected);
        $tests = 0;

        foreach($settings as $key => $value) {

            $expectedKey = array_shift($keys);
            $expectedValue = array_shift($values);

            $this->assertEquals($expectedKey, $key, 'Key is wrong.');
            $this->assertEquals($expectedValue, $value, 'Value is wrong.');

            $tests++;
        }

        $this->assertNotEquals(0, $tests, 'No tests were run!');

    }

    function testArrayAccess() {

        $settings = new Octopus_Settings();
        $settings->addFromYaml(<<<END
site_lang:
  default: en-us
site_name:
  default: Default Site Name
END
        );

        $this->assertTrue(isset($settings['site_lang']), 'site_lang is set');
        $this->assertEquals('en-us', $settings['site_lang']);

        $settings['site_lang'] = 'fr';
        $this->assertEquals('fr', $settings->get('site_lang'));

        unset($settings['site_lang']);
        $this->assertEquals('en-us', $settings['site_lang']);

    }

    function dontTestLoadFromPHP() {

        $phpFile = $this->testDir . '/php_test.php';

        file_put_contents(
            $phpFile,
            <<<END
<?php

    return array(

        'setting1' => array(
            'default' => 'foo',
        ),

        'setting2' => array(
            'default' => 'bar'
        )

    );

?>
END
        );

        $settings = new Octopus_Settings();
        $settings->addFromFile($phpFile);

        $ar = $settings->toArray();

        $this->assertEquals(
            array(
                'setting1' => 'foo',
                'setting2' => 'bar'
            ),
            $ar
        );

    }

    function testWildcards() {

        $ar = array(
            'wildcard.setting.*' => array(
                'default' => 'foo'
            )
        );

        $settings = new Octopus_Settings($ar);

        $this->assertEquals('foo', $settings->get('wildcard.setting'));
        $this->assertEquals('foo', $settings->get('wildcard.setting.something'));
        $this->assertEquals('foo', $settings->get('wildcard.setting.something.else'));
        $this->assertEquals(null, $settings->get('wildcard'));

        $settings->set('wildcard.setting', 'bar');

        $this->assertEquals('bar', $settings->get('wildcard.setting'));
        $this->assertEquals('bar', $settings->get('wildcard.setting.something'));
        $this->assertEquals('bar', $settings->get('wildcard.setting.something.else'));
        $this->assertEquals(null, $settings->get('wildcard'));

        $settings->reload();

        $this->assertEquals('bar', $settings->get('wildcard.setting'));
        $this->assertEquals('bar', $settings->get('wildcard.setting.something'));
        $this->assertEquals('bar', $settings->get('wildcard.setting.something.else'));
        $this->assertEquals(null, $settings->get('wildcard'));


        $settings->set('wildcard.setting.something', 'baz');
        $this->assertEquals('bar', $settings->get('wildcard.setting'));
        $this->assertEquals('baz', $settings->get('wildcard.setting.something'));
        $this->assertEquals('baz', $settings->get('wildcard.setting.something.else'));
        $this->assertEquals(null, $settings->get('wildcard'));

        $settings->reload();

        $this->assertEquals('bar', $settings->get('wildcard.setting'));
        $this->assertEquals('baz', $settings->get('wildcard.setting.something'));
        $this->assertEquals('baz', $settings->get('wildcard.setting.something.else'));
        $this->assertEquals(null, $settings->get('wildcard'));

    }

    function testAddingSubkeyOfWildcardModfiesOriginalKey() {

        $settings = new Octopus_Settings();
        $settings->addFromArray(
            array(
                'my.key.*' => array('default' => 'foo'),
                'my.key.something.somethingelse' => array('default' => 'bar'),
                'my.key.something.somethingelse.andagain.andagain' => array('default' => 'baz')
            )
        );

        $tests = array(
            'my.key' => 'foo',
            'my.key.something' => 'foo',
            'my.key.blarg' => 'foo',
            'my.key.something.somethingelse' => 'bar',
            'my.key.something.somethingelse.andagain' => 'bar',
            'my.key.something.somethingelse.andagain.andagain' => 'baz',
            'my.key.something.somethingelse.andagain.andagain.thisisnuts' => 'baz',
        );

        foreach($tests as $input => $expected) {
            $this->assertEquals($expected, $settings->get($input), "Failed on $input");
        }

    }

    function testOverwriteWildcard() {

        $settings = new Octopus_Settings();
        $settings->addFromArray(array(
            'my.setting.*' => array('default' => 'foo'),
        ));
        $settings->addFromArray(
            array(
                'my.setting.*' => array('default' => 'bar')
            )
        );

        $tests = array(
            'my' => null,
            'my.setting' => 'bar',
            'my.setting.foo' => 'bar'
        );

        foreach($tests as $input => $expected) {
            $this->assertEquals($expected, $settings->get($input), "Failed on $input");
        }

    }

    function testGetThemeForSubDir() {

        $settings = new Octopus_Settings();
        $settings->addFromArray(
            array(
                'site.theme.*' => array('default' => 'foo'),
                'site.theme.admin' => array('default' => 'admin')
            )
        );

        $tests = array(

            'site.theme' => 'foo',
            'site.theme.whatever' => 'foo',
            'site.theme.admin' => 'admin',
            'site.theme.admin.subdir' => 'admin',
            'site.theme.whatever.admin' => 'foo'

        );

        foreach($tests as $input => $expected) {

            $this->assertEquals($expected, $settings->get($input), "Failed on $input");

        }

    }

    function defaultSettingValueFunction() {
    	return $this->defaultUsingFunctionValue;
    }
    private $defaultUsingFunctionValue;

    function testFunctionForDefault() {

		$settings = new Octopus_Settings();
		$settings->addFromArray(array(
			'some.setting' => array('default_func' => array($this, 'defaultSettingValueFunction'))
		));

		$this->defaultUsingFunctionValue = 42;
		$this->assertEquals(42, $settings->get('some.setting'));

		$settings->set('some.setting', '99');
		$this->assertEquals(99, $settings->get('some.setting'));

    }

}

?>
