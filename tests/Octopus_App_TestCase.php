<?php

/**
 * TestCase that creates its own sitedir to work within.
 */
abstract class Octopus_App_TestCase extends PHPUnit_Framework_TestCase {

    protected $siteDir;
    protected $app;

    function setUp() {
        $this->siteDir =  dirname(__FILE__) . '/.working/' . get_called_class() . '-sitedir';
        $this->initSiteDir();
    }

    function tearDown() {
        //$this->cleanUpSiteDir();
    }

    function initSiteDir() {

        $this->cleanUpSiteDir();

        $s = $this->siteDir;
        mkdir($s, 0777, true);
        mkdir("$s/controllers");
        mkdir("$s/views");
        mkdir("$s/models");
        mkdir("$s/themes/default/templates/html", 0777, true);
        file_put_contents("$s/themes/default/templates/html/page.php", '<?php echo $view_content; ?>');

    }

    function cleanUpSiteDir() {
        `rm -rf {$this->siteDir}`;
    }

    /**
     * Asserts two chunks of HTML are equal.
     */
    public function assertHtmlEquals() {
        $args = func_get_args();
        array_unshift($args, $this);
        call_user_func_array(array('Octopus_Html_TestCase', 'staticAssertHtmlEquals'), $args);
    }

    function createViewFile($path, $contents = null) {

        if (!is_array($path)) {
            $path = array($path);
        }

        $result = array();
        $viewDir = $this->app->getSetting('SITE_DIR') . 'views/';

        foreach($path as $p) {

            $file = $viewDir . $p;

            if (!(ends_with($p, '.php', true) || ends_with($p, '.tpl', true))) {
                $file .= '.php';
            }

            recursive_touch($file);

            if ($contents === null) {
                $fileContents = $p;
            } else {
                $fileContents = $contents;
            }

            file_put_contents($file, $fileContents);

            $result[] = $file;
        }

        if (count($result) == 1) {
            return array_shift($result);
        }

        return $result;

    }

    function createControllerFile($path, $contents = null) {

        if (!is_array($path)) {
            $path = array($path);
        }

        $result = array();
        $controllerDir = $this->app->getSetting('SITE_DIR') . 'controllers/';

        foreach($path as $p) {

            $name = basename($p);
            $file = $controllerDir . $p . '.php';

            recursive_touch($file);

            if ($contents === null) {
                $fileContents = "<?php class {$name}Controller extends Octopus_Controller { } ?>";
            } else {
                $fileContents = $contents;
            }

            file_put_contents($file, $fileContents);
            $result[] = $file;
        }

        if (count($result) == 1) {
            return array_shift($result);
        }

        return $result;
    }

    protected function getApp() {
        if ($this->app) {
            return $this->app;
        }
        return ($this->app = $this->startApp());
    }

    /**
     * Starts an app instance for testing.
     */
    protected function startApp($options = array()) {

        $defaults = array(
            'use_defines' => false,
            'use_globals' => false,
            'SITE_DIR' => $this->siteDir
        );

        if (empty($options)) {
            $options = $defaults;
        } else {
            $options = array_merge($defaults, $options);
        }

        return $this->app = Octopus_App::start($options);
    }


    protected function assertControllerInfoMatches($expected, $info, $path = null) {

        if ($info instanceof Octopus_Request) {
            $path = $info->getPath();
            $info = $info->getControllerInfo();
        }

        if (is_string($expected)) {
            $expected = array('file' => $expected);
        }

        if ($expected === false) {
            $this->assertFalse($info, "Failed on '$path'");
            return;
        }

        $this->assertTrue(is_array($info), "\$info was not an array. Failed on '$path'");

        foreach($expected as $key => $value) {
            $this->assertEquals($value, $info[$key], "Failed on '$key' for path '$path'");
        }
    }

}

?>
