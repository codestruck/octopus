<?php

require_once 'PHPUnit/Extensions/OutputTestCase.php';

/**
 * TestCase that creates its own sitedir to work within.
 */
abstract class Octopus_App_TestCase extends PHPUnit_Extensions_OutputTestCase {

    private $siteDir;
    protected $app;

    public function __construct($name = NULL, array $data = array(), $dataName = '') {

        parent::__construct($name, $data, $dataName);

        // For system tests, create a custom sitedir per-testcase
        $this->siteDir =  dirname(__FILE__) . '/.working/' . get_called_class() . '-sitedir/';

        if (defined('SITE_DIR')) {
            
            // NOTE: __FILE__ is Octopus_App_TestCase.php, but by using 
            // reflection we can get the actual file the running class is
            // defined in.
            $c = new ReflectionClass($this);
            $file = $c->getFileName();

            if (starts_with($file, SITE_DIR)) {
                // We are running a site test, so use the defined site dir
                $this->siteDir = SITE_DIR;
            }

        }

    }

    function setUp() {
        
        $this->initSiteDir();

        $this->clear($_GET);
        $this->clear($_POST);

        $this->startApp();
    }


    public function getOctopusDir() {
        if ($this->app) {
            return $this->app->getOption('OCTOPUS_DIR');
        } else {
            return OCTOPUS_DIR;
        }
    }

    public function getOctopusDirUrl() {
        return $this->getDirUrl($this->getOctopusDir());
    }

    public function getSiteDir() {
        return $this->siteDir;
    }

    public function getDirUrl($dir) {
        $urlBase = $this->app ? $this->app->getOption('URL_BASE') : '/';
        $rootDir = $this->app ? $this->app->getOption('ROOT_DIR') : ROOT_DIR;

        $url = $dir;
        if (starts_with($url, $rootDir)) {
            $url = substr($url, strlen($rootDir));
        }
        $url = trim($url, '/');
        if ($url) $url .= '/';

        return $urlBase . $url;        
    }

    public function getSiteDirUrl() {
        return $this->getDirUrl($this->getSiteDir());
    }

    private function clear(&$ar) {
        foreach($ar as $key => $value) {
            unset($ar[$key]);
        }
    }

    function tearDown() {

        //$this->cleanUpSiteDir();

        if ($this->app) {
            $this->app->stop();
            $this->app = null;
        }
        
    }

    function initSiteDir() {

        $this->cleanUpSiteDir();

        $s = $this->getSiteDir();
        mkdir($s, 0777, true);
        mkdir("$s/controllers");
        mkdir("$s/views");
        mkdir("$s/models");
        mkdir("$s/themes/default/templates/html", 0777, true);
        file_put_contents("$s/themes/default/templates/html/page.php", '<?php echo $view_content; ?>');

    }

    function cleanUpSiteDir() {
        $dir = $this->getSiteDir();
        `rm -rf {$dir}`;
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

        if ($this->app) {
            $this->app->stop();
            $this->app = null;
        }

        $defaults = array(
            'use_defines' => false,
            'use_globals' => false,
            'SITE_DIR' => $this->getSiteDir()
        );

        if (empty($options)) {
            $options = $defaults;
        } else {
            $options = array_merge($defaults, $options);
        }

        if (Octopus_App::isStarted()) {
            $oldApp = Octopus_App::singleton();
            $oldApp->stop();
        }

        $this->app = Octopus_App::start($options);
        $this->assertTrue(!!$this->app, 'Octopus_App::start() did not return an app instance.');
        $this->assertSame($this->app, Octopus_App::singleton(), "Test's app instance is available in global context");

        return $this->app;
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
