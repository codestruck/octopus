<?php

class EnvironmentTest extends Octopus_App_TestCase {


    function testSiteModelsAvailable() {

        file_put_contents(
            $this->siteDir . '/models/FooTestSiteModelsAvailable.php',
            <<<END
<?php
class FooTestSiteModelsAvailable extends Octopus_Model {}
?>
END
        );

        $this->startApp();
        $this->assertTrue(class_exists('FooTestSiteModelsAvailable'), 'models not loaded by default');

    }

}

?>
