<?php

SG::loadClass('SG_Settings');

class SettingsTest extends SG_DB_TestCase {

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

        $settings = new SG_Settings();

        $this->assertEquals('Project Octopus!', $settings->get('site_name'));
        $this->assertEquals(0.1, $settings->get('site_version'));

    }

    function testWriteToDB() {

        $settings = new SG_Settings();
        $settings->set('foo', 'bar');

        $settings = new SG_Settings();
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

        $settings = new SG_Settings();
        $settings->addFromFile($file);

        $settings->set('name', 'Matt');
        $settings  = new SG_Settings();

        $this->assertEquals('Matt', $settings->get('name'));

        $settings->reset('name');
        $settings = new SG_Settings();
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

        $settings = new SG_Settings();
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

        $settings = new SG_Settings();
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

}

?>
