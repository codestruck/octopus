<?php

class SysControllerTests extends Octopus_App_TestCase {

    function testSysControllerNotAvailableOutsideDev() {

        $s = $this->getSiteDir();
        @mkdir("$s/views/sys");
        touch("$s/views/sys/forbidden.php");

        file_put_contents("$s/themes/default/templates/html/page.php", '<?php echo $view_content; ?>');

        $states = array(
            'DEV' => true,
            'LIVE' => false,
            'STAGING' => false
        );

        foreach($states as $state => $available) {

            $app = $this->startApp(array($state => true));

            $resp = $app->getResponse('sys/about', true);

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
