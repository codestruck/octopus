<?php

/**
 * TestCase that creates its own sitedir to work within.
 */
abstract class Octopus_App_TestCase extends PHPUnit_Framework_TestCase {

    protected $siteDir;

    public function __construct() {
        parent::__construct();
        $this->siteDir =  '.' . get_class() . '-sitedir';
    }

    function setUp() {

        $this->initSiteDir();

    }

    function tearDown() {
        $this->cleanUpSiteDir();
    }

    function initSiteDir() {

        $this->cleanUpSiteDir();

        $s = $this->siteDir;
        mkdir($s);
        mkdir("$s/controllers");
        mkdir("$s/views");
        mkdir("$s/themes/default/templates/html", 0777, true);
        file_put_contents("$s/themes/default/templates/html/page.php", '<?php echo $view_content; ?>');

    }

    function cleanUpSiteDir() {
        `rm -rf {$this->siteDir}`;
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
