<?php

SG::loadClass('SG_Settings');

class SettingsTest extends PHPUnit_Framework_TestCase {

    public $testDir = '.settings-test';

    function setUp() {

        $this->deleteTestDir();
        mkdir($this->testDir);

    }

    function deleteTestDir() {
        `rm -rf {$this->testDir}`;
    }

    function tearDown() {
        $this->deleteTestDir();
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


}

?>
