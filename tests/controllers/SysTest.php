<?php
/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class SysControllerTests extends Octopus_App_TestCase {

    function testSysControllerNotAvailableOutsideDev() {

        $s = $this->getSiteDir();
        @mkdir("$s/views/sys");
        touch("$s/views/403.tpl");

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
                $this->assertEquals(404, $resp->getStatus(), "should be not found under $state");

                $this->assertHtmlEquals(
                    <<<END
<h1>Not Found</h1>

<p>
    The page you were looking for could not be found.
</p>
END
                    ,
                    $resp->render(true)
                );
            }
        }

    }

}

?>
