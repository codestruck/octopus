<?php

class EnvironmentTest extends Octopus_App_TestCase {


    function testSiteModelsAvailable() {

        file_put_contents(
            $this->getSiteDir() . '/models/FooTestSiteModelsAvailable.php',
            <<<END
<?php
class FooTestSiteModelsAvailable extends Octopus_Model {}
?>
END
        );

        $this->startApp();
        $this->assertTrue(class_exists('FooTestSiteModelsAvailable'), 'models not loaded by default');

    }

    function testDev() {
        $this->assertTrue(is_dev_environment(null, null, false, 'dvsas.local'));
        $this->assertTrue(is_dev_environment(null, null, false, 'dvsas.estes'));

        $this->assertFalse(is_dev_environment(null, null, false, 'dvsas.com'));
        $this->assertFalse(is_dev_environment(null, null, false, 'dvsas.org'));
    }

    function testStaging() {
        $this->assertFalse(is_staging_environment(null, null, false, 'dvsas.local', '/'));
        $this->assertFalse(is_staging_environment(null, null, false, 'dvsas.com', '/'));
        $this->assertTrue(is_staging_environment(null, null, false, 'dev.dvsas.org', '/'));
        $this->assertTrue(is_staging_environment(null, null, false, 'dvsas.org', '/dev/'));
    }


}

?>
