<?php

/**
 * TestCase that creates its own sitedir to work within.
 */
abstract class Octopus_App_TestCase extends PHPUnit_Framework_TestCase {

    private $app;
    private $testRootDir;
    private static $sessionID = null;

    function setUp() {
    	Octopus_Debug::reset();
        $this->initEnvironment();
        $this->clear($_GET);
        $this->clear($_POST);

        // Get rid of any existing app instance by starting a fresh one.
        // (This line is important.)
        $this->startApp();
    }

    function tearDown() {
        if ($this->app) {
            $this->app->stop();
            $this->app = null;
        }
    }

    public function getCacheDir() {
        return $this->getRootDir() . 'cache/';
    }

    public function getOctopusDir() {
        return $this->getRootDir() . 'octopus/';
    }

    public function getOctopusDirUrl() {
        return $this->getDirUrl($this->getOctopusDir());
    }

    public function getRootDir() {
        if (!$this->testRootDir) {
            $this->initEnvironment();
        }
        return $this->testRootDir;
    }

    public function getPrivateDir() {
        return $this->getRootDir() . '_private/';
    }

    public function getSiteDir() {
        return $this->getRootDir() . 'site/';
    }

    public function getSiteDirUrl() {
        return $this->getDirUrl($this->getSiteDir());
    }

    public function getDirUrl($dir) {

        $app = $this->getApp();

        $urlBase = $app->getOption('URL_BASE');
        $rootDir = $this->getRootDir();

        $url = $dir;
        if (starts_with($url, $rootDir)) {
            $url = substr($url, strlen($rootDir));
        }
        $url = trim($url, '/');
        if ($url) $url .= '/';

        return $urlBase . $url;
    }


    private function clear(&$ar) {
        foreach($ar as $key => $value) {
            unset($ar[$key]);
        }
    }


    /**
     * Sets up a walled-off bit of the directory tree in which to run
     * our tests.
     */
    protected function initEnvironment() {

        if (Octopus_App::isStarted()) {
            $app = Octopus_App::singleton();
            $app->stop();
        }

        if (!self::$sessionID) {
            self::$sessionID = md5(uniqid());
        }

        $tries = 0;
        $key = '';
        $report = true;

        while(1) {

            if ($tries >= 1) {
                die("Could not set up environment. Tried $tries times.");
            }

            $tries++;

            $this->testRootDir = rtrim(sys_get_temp_dir(), '/') . '/octopus-tests' . $key . '/' . self::$sessionID . '/' . get_called_class() . '/';
            $key = '/' . mt_rand(0, 1000000);

            $failures = array();
            if (is_dir($this->testRootDir)) {
                if (!recursive_delete($this->testRootDir, true, $failures)) {
                    if ($report) {
                        dump_r("testRootDir already exists and could not be deleted.", $this->testRootDir, $failures);
                    }
                    continue;
                }
                if (is_dir($this->testRootDir)) {
                    if ($report) {
                        dump_r("testRootDir existed, and recursive_delete returned true, but the directory is still there.", $failures);
                    }
                    continue;
                }
            }

            if (!@mkdir($this->testRootDir, 0777, true)) {
                if ($report) dump_r("mkdir failed for testRootDir: {$this->testRootDir}");
                continue;
            }

            $octopusLinkTarget = dirname(dirname(__FILE__));
            $octopusLinkName = rtrim($this->getOctopusDir(), '/');

            if (!@symlink($octopusLinkTarget, $octopusLinkName)) {
                if ($report) dump_r("Failed to symlink in octopus directory: $octopusLinkTarget -> $octopusLinkName");
                continue;
            }

            if (defined('SITE_DIR')) {

                $siteDirLinkTarget = rtrim(SITE_DIR, '/');
                $siteDirLinkName = rtrim($this->getSiteDir(), '/');

                if (!@symlink(rtrim(SITE_DIR, '/'), rtrim($this->getSiteDir(), '/'))) {
                    if ($report) dump_r("Failed to symlink in site dir: $siteDirLinkTarget -> $siteDirLinkName");
                    continue;
                }

            } else {
                // Create a working site dir
                $siteDir = $this->getSiteDir();
                mkdir($siteDir);
                mkdir("$siteDir/controllers");
                mkdir("$siteDir/views");
                mkdir("$siteDir/models");
                mkdir("$siteDir/themes/default/templates/html", 0777, true);
                file_put_contents("$siteDir/themes/default/templates/html/page.php", '<?php echo $view_content; ?>');
            }

            break;
        }
    }

    /**
     * Asserts two chunks of HTML are equal.
     */
    public function assertHtmlEquals() {
        $args = func_get_args();
        array_unshift($args, $this);
        call_user_func_array(array('Octopus_Html_TestCase', 'staticAssertHtmlEquals'), $args);
    }

    function assertSmartyEquals($expected, $value, $message = '', $replaceMD5 = false, $replaceMtime = false, $assign = array()) {

        $app = $this->getApp();

        $s = Octopus_Smarty::singleton();

        $smartyDir = $this->getSiteDir() . 'smarty/';
        @mkdir($smartyDir);

        $tplFile = $smartyDir . 'test.' . md5($expected) . '.tpl';
        @unlink($tplFile);

        file_put_contents($tplFile, $value);

        $s->smarty->template_dir = array($smartyDir);

        $tpl = $s->smarty->createTemplate($tplFile);
        $tpl->assign($assign);
        $rendered = $tpl->fetch();

        if ($replaceMD5) {
            $rendered = preg_replace('/[a-f\d]{32}/i', '[MD5]', $rendered);
        }

        if ($replaceMtime) {
            $rendered = preg_replace('/\d{10,}/', '[MTIME]', $rendered);
        }

        $this->assertHtmlEquals($expected, $rendered, $message);
    }

    function createViewFile($path, $contents = null) {

        if (!is_array($path)) {
            $path = array($path);
        }

        $result = array();
        $viewDir = $this->getApp()->getSetting('SITE_DIR') . 'views/';

        foreach($path as $p) {

            $file = $viewDir . $p;

            if (!(ends_with($p, '.php', true) || ends_with($p, '.tpl', true) || ends_with($p, '.mustache'))) {
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
        $controllerDir = $this->getApp()->getSetting('SITE_DIR') . 'controllers/';

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
            'ROOT_DIR' => $this->getRootDir(),
            'OCTOPUS_DIR' => $this->getOctopusDir(),
            'SITE_DIR' => $this->getSiteDir(),
            'OCTOPUS_PRIVATE_DIR' => $this->getPrivateDir(),
            'OCTOPUS_CACHE_DIR' => $this->getCacheDir()
        );

        $options = array_merge($defaults, $options);

        if (Octopus_App::isStarted()) {
            $oldApp = Octopus_App::singleton();
            $oldApp->stop();
        }

        $this->app = Octopus_App::start($options);
        $this->assertTrue(!!$this->app, 'Octopus_App::start() did not return an app instance.');
        $this->assertSame($this->app, Octopus_App::singleton(), "Test's app instance is available in global context");

        $this->app->clearFullCache();

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
