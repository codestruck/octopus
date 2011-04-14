<?php

class AppTests extends PHPUnit_Framework_TestCase {

    function dontTestDetectDevEnvironment() {

        $app = SG_App::start(array(
        ));

        $this->assertTrue($app->isDevEnvironment(), 'should be in dev environment');
    }

}

?>
