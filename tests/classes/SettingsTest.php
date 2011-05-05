<?php

Octopus::loadClass('Octopus_Settings');

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

        $db->query('DROP TABLE IF EXISTS settings');

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

    function testToArray() {

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
            array(
                'age' => 20,
                'name' => 'Joe Blow',
                'site_name' => 'Project Octopus!',
                'site_version' => 0.1
            ),
            $settings->toArray()
        );

        $settings->set('name', 'Matt');
        $this->assertEquals(
            array(
                'age' => 20,
                'name' => 'Matt',
                'site_name' => 'Project Octopus!',
                'site_version' => 0.1
            ),
            $settings->toArray()
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

}

?>
