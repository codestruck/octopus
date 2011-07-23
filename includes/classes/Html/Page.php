<?php

class Octopus_Html_Page {

    private static $instance = null;

    public static $defaults = array(
        'titleSeparator' => ' | '
    );

    protected $options;

    protected $stylesheets = array();
    protected $scripts = array();
    protected $vars = array();
    protected $meta = array();
    protected $links = array();

    protected $fullTitle = null;
    protected $title = null;
    protected $subtitles = array();
    protected $titleSeparator = ' | ';
    protected $breadcrumbs = array();

    public function __construct($options = array()) {

        $this->options = array_merge(self::$defaults, $options);

        if (!isset($this->options['URL_BASE'])) {

            if (class_exists('Octopus_App') && Octopus_App::isStarted()) {
                $app = Octopus_App::singleton();
                $this->options['URL_BASE'] = $app->getOption('URL_BASE');
            } else {
                $this->options['URL_BASE'] = find_url_base();
            }

        }


    }

    /**
     * Adds a breadcrumb to this page. Breadcrumbs should be added in order
     * from least specific to most specific, e.g.:
     * <example>
     * $page->addBreadcrumb('/products', 'Products');
     * $page->addBreadcrumb('/products/shirts', 'Shirts');
     * </example>
     *
     * Will result in:
     *
     * Home > Products > Shirts
     *
     * And produce a default full title of:
     *
     * (Page Title) | Shirts | Products
     *
     */
    public function addBreadcrumb($url, $text) {

        $url = $this->u($url);
        $this->breadcrumbs[$url] = $text;
        $this->subtitles[] = $text;

        return $this;
    }

    public function removeBreadcrumb($url) {
        $url = $this->u($url);
        unset($this->breadcrumbs[$url]);
        return $this;
    }

    public function removeAllBreadcrumbs() {
        $this->breadcrumbs = array();
    }

    public function getBreadcrumbs() {
        return $this->breadcrumbs;
    }

    /**
     * Helper that calls u() with the appropriate args.
     */
    protected function u($url) {
        return u($url, null, array('URL_BASE' => $this->options['URL_BASE']));
    }

    public function getFullTitle() {
        if ($this->fullTitle !== null) {
            return $this->fullTitle;
        } else {
            return $this->buildFullTitle();
        }
    }

    public function setFullTitle($fullTitle) {

        if ($fullTitle === null || $fullTitle === false) {
            $this->fullTitle = null;
        } else {
            $this->fullTitle = $fullTitle;
        }

        return $this;
    }

    /**
     * Resets the full title to the default.
     */
    public function resetFullTitle() {
        return $this->setFullTitle(null);
    }

    public function getTitle() {
        return $this->title;
    }

    public function setTitle($title) {
        $this->title = $title;
        return $this;
    }

    public function getTitleSeparator() {
        return $this->options['titleSeparator'];
    }

    public function setTitleSeparator($sep) {
        $this->options['titleSeparator'] = $sep;
    }

    /**
     * Given all the available elements, assembles a full title like
     * <example>
     * Page Title | Site Name
     * </example>
     */
    protected function buildFullTitle() {

        $result = '';
        $sep = $this->options['titleSeparator'];

        foreach($this->subtitles as $subtitle) {

            $subtitle = trim($subtitle);
            if ($subtitle) {
                if ($result) $result = $sep . $result;
                $result = $subtitle . $result;
            }

        }

        $title = trim($this->getTitle());
        if ($title) {
            if ($result) $result = $sep . $result;
            $result = $title . $result;
        }

        return $result;
    }

    public static function singleton() {
        if (!self::$instance) {
            self::$instance = new Octopus_Html_Page();
        }
        return self::$instance;
    }


}


?>
