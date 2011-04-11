<?php

class SysControllerTests extends SG_App_TestCase {

    function testSysControllerNotAvailableOutsideDev() {

        $s = $this->siteDir;
        @mkdir("$s/views/sys");
        touch("$s/views/sys/forbidden.php");

        $states = array(
            'DEV' => true,
            'LIVE' => false,
            'STAGING' => false
        );

        foreach($states as $state => $available) {

            $app = $this->startApp(array($state => true));

            $resp = $app->getResponse('sys/about');

            if ($available) {
                $this->assertEquals(200, $resp->getStatus(), "sys/about should be available under $state");
            } else {
                $this->assertEquals(403, $resp->getStatus(), "should be forbidden under $state");
                $this->assertEquals('', $resp->getContent(), "content should be empty under $state");
            }
        }

    }

}

?>
