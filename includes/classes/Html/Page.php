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

    public function getJavascriptVar($name, $default = null) {
        return isset($this->vars[$name]) ? $this->vars[$name]['value'] : $default;
    }

    /**
     * @return Array of defined javascript variables.
     */
    public function &getJavascriptVars() {

        $result = array();

        foreach($this->vars as $name => $info) {
            $result[$name] = $info['value'];
        }

        return $result;
    }

    /**
     * Sets a global javascript variable.
     * @param $name Name of the variable.
     * @param $value Value for the variable.
     * @param $priority Order in which variable should be set. Higher
     * priority = render sooner.
     */
    public function setJavascriptVar($name, $value, $priority = 0) {
        $this->vars[$name] = array('value' => $value, 'priority' => $priority);
        return $this;
    }

    /**
     * @param $vars Array of variables to set.
     */
    public function setJavascriptVars($vars) {
        foreach($vars as $var => $value) {
            $this->setJavascriptVar($var, $value);
        }
        return $this;
    }

    /**
     * Generates the HTML for the Javascript variables section.
     */
    public function renderJavascriptVars($return = false) {

        if (empty($this->vars)) {
            if ($return) {
                return '';
            } else {
                return;
            }
        }

        uasort($this->vars, array('Octopus_Html_Page', 'comparePriorities'));

        $html = <<<END
<script type="text/javascript">

END;

        foreach($this->vars as $name => $value) {
            $value = json_encode($value['value']);
            $html .= <<<END
var $name = $value;

END;
        }

        $html .= <<<END
</script>

END;

        if ($return) {
            return $html;
        }

        echo $html;
    }

    private static function comparePriorities($x, $y) {

        $x = isset($x['priority']) ? $x['priority'] : 0;
        $y = isset($y['priority']) ? $y['priority'] : 0;

        return $y - $x;
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
