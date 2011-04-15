<?php

class AppTests extends PHPUnit_Framework_TestCase {

    function testdontTestDetectDevEnvironment() {
        $this->markTestIncomplete();
        $app = SG_App::start(array(
        ));

        $this->assertTrue($app->isDevEnvironment(), 'should be in dev environment');
    }

}

?>
