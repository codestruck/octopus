<?php

class ControllerDiscoveryTest extends PHPUnit_Framework_TestCase {

    static $testDir = 'controller-discovery';

    function setUp() {
        $this->initTestDir();
    }

    function tearDown() {
        $this->deleteTestDir();
    }

    function initTestDir() {

        $d = self::$testDir;
        $this->deleteTestDir();

        mkdir($d);
        mkdir("$d/octopus");
        mkdir("$d/site");
        mkdir("$d/octopus/controllers");
        mkdir("$d/site/controllers");

        $o = "$d/octopus/controllers";
        touch("$o/Basic.php");
        touch("$o/Admin_Basic.php");
        touch("$o/Really_Admin_Basic.php");
        touch("$o/Overridden_By_Site.php");
        touch("$o/Also_Overridden_By_Site.php");

        $s = "$d/site/controllers";
        touch("$s/Overridden.php");
        touch("$s/Also_Overridden_By_Site.php");
        touch("$s/Custom.php");


    }

    function deleteTestDir() {
        $d = self::$testDir;
        `rm -rf $d`;
    }

    function testDiscoverControllers() {

        $app = Octopus_App::start(array(
            'OCTOPUS_DIR' => self::$testDir . "/octopus",
            'SITE_DIR' => self::$testDir . "/site",
            'ROOT_DIR' => ROOT_DIR,
            'OCTOPUS_INCLUDES_DIR' => OCTOPUS_INCLUDES_DIR,
            'use_site_config' => false
        ));

        $controllers = $app->getControllers(true);
        sort($controllers);

        $this->assertEquals(
            array(
                'Admin_Basic',
                'Also_Overridden_By_Site',
                'Basic',
                'Custom',
                'Overridden_By_Site',
                'Really_Admin_Basic'
            ),
            $controllers
        );





    }


}

?>
