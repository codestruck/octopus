<?php

/**
 * TestCase that creates its own sitedir to work within.
 */
abstract class Octopus_App_TestCase extends PHPUnit_Framework_TestCase {

    protected $siteDir;

    public function __construct() {
        parent::__construct();
        $this->siteDir =  dirname(__FILE__) . '/.working/' . get_called_class() . '-sitedir';
    }

    function setUp() {
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

        return Octopus_App::start($options);
    }

}

?>
