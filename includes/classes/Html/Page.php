<?php

Octopus::loadClass('Octopus_Html_Element');

class Octopus_Html_Page {

    private static $instance = null;
    private static $counter = 0;

    public static $defaults = array(
        'titleSeparator' => ' | '
    );

    protected $options;

    protected $css = array();
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

        $this->setMeta('Content-type', 'text/html; charset=UTF-8');
    }

    private static function counter() {
        return self::$counter++;
    }

    /**
     * Sets a meta attribute on this page.
     */
    public function setMeta($key, $value) {

        $normalKey = strtolower($key);

        if ($value === null) {
            unset($this->meta[$normalKey]);
        } else {
            $this->meta[$normalKey] = compact('key', 'value');
        }

        return $this;
    }

    public function getMeta($key, $default = null) {
        $key = strtolower($key);
        return isset($this->meta[$key]) ? $this->meta[$key]['value'] : $default;
    }

    public function removeMeta($key) {
        return $this->setMeta($key, null);
    }

    /**
     * Outputs the meta tags section.
     */
    public function renderMeta($return = false) {

        if (empty($this->meta)) {
            return $return ? '' : $this;
        }

        $html = '';

        foreach($this->meta as $key => $item) {

            $el = new Octopus_Html_Element('meta');
            list($keyAttr, $valueAttr) = self::getMetaAttributes($key);
            $el->$keyAttr = $item['key'];
            $el->$valueAttr = $item['value'];

            $html .= $el->render(true);
        }

        if ($return) {
            return $html;
        }

        echo $html;
        return $this;
    }

    private static function getMetaAttributes($key) {

        switch($key) {

            case 'cache-control':
            case 'content-language':
            case 'content-location':
            case 'content-type':
            case 'date':
            case 'expires':
            case 'last-modified':
            case 'location':
            case 'pragma':
            case 'refresh':
            case 'set-cookie':
            case 'window-target':

            // NOTE The html validator complains about using http-equiv for imagetoolbar, but oh well
            case 'imagetoolbar':

                return array('http-equiv', 'content');

            default:

                if (starts_with($key, 'og:')) {
                    return array('property', 'content');
                }

                return array('name', 'content');
        }
    }

    /**
     * @return string The content-type of the page.
     */
    public function getContentType() {

        $type = strtolower($this->getMeta('Content-type', 'text/html'));

        $pos = strpos($type, ';');
        if ($pos === false) {
            return $type;
        }

        return trim(substr($type, 0, $pos));
    }

    public function setContentType($type) {

        $existing = $this->getMeta('Content-type', 'text/html; charset=UTF-8');

        if (isset($this->meta['content-type'])) {
            $existing = $this->meta['content-type'];
        }

        if (preg_match('/(;.*)/', $existing['value'], $m)) {
            return $this->setMeta('Content-type', $type . $m[1]);
        }

        return $this->setMeta('Content-type', $type . '; charset=UTF-8');
    }

    public function getCharset() {

        $contentType = $this->getMeta('Content-type');
        if (!$contentType) {
            return false;
        }

        if (preg_match('/;\s*charset\s*=\s*([^;]+)/i', $contentType, $m)) {
            return trim($m[1]);
        }

        return false;
    }

    public function setCharset($charset) {

        $existing = $this->getMeta('Content-type');

        if ($existing) {
            $new = preg_replace('/;\s*charset\s*=\s*[^;]+/i', '; charset=' . $charset . ' ', $existing, -1, $count);
            if ($count === 0) {
                $new = $existing . '; charset=' . $charset;
            }
            return $this->setMeta('Content-type', trim($new));
        }

        return $this->setMeta('Content-type', 'text/html; charset=' . $charset);

    }

    /**
     * @return Number Timestamp when this page expires, or false if it does not
     * expire.
     */
    public function getExpiryDate() {

        $expires = $this->getMeta('Expires');
        if ($expires === null) return false;

        return strtotime($expires);
    }

    public function setExpiryDate($date) {

        $this->removeMeta('Cache-control');
        $this->removeMeta('Pragma');

        if ($date === false) {
            $this->removeMeta('Expires');
            return $this;
        }

        if (is_string($date)) {
            $date = strtotime($date);
        }

        return $this->setMeta('Expires', date('r', $date));
    }

    public function isExpired() {

        $expiry = $this->getExpiryDate();
        if ($expiry === null || $expiry === false) return false;

        return $expiry < time();
    }

    public function setExpired($expired) {

        if ($expired) {
            $this->setExpiryDate(0);
            $this->setMeta('Cache-control', 'no-cache');
            // TODO: technically, there can be more than 1 pragma
            $this->setMeta('Pragma', 'no-cache');
        } else {
            return $this->removeMeta('Expires');
        }

    }

    public function setDescription($desc) {
        return $this->setMeta('description', $desc);
    }

    public function getDescription() {
        return $this->getMeta('description');
    }

    public function setKeywords($keywords) {
        return $this->setMeta('keywords', $keywords);
    }

    public function getKeywords() {
        return $this->getMeta('keywords');
    }

    public function setAuthor($author) {
        return $this->setMeta('author', $author);
    }

    public function getAuthor() {
        return $this->getMeta('author');
    }

    public function setCopyright($copyright) {
        return $this->setMeta('copyright', $copyright);
    }

    public function getCopyright() {
        return $this->getMeta('copyright');
    }

    public function setContact($contact) {
        return $this->setMeta('contact', $contact);
    }

    public function getContact() {
        return $this->getMeta('contact');
    }

    public function setRobots($robots) {
        return $this->setMeta('robots', $robots);
    }

    public function getRobots() {
        return $this->getMeta('robots');
    }

    public function getCanonicalUrl() {

        $link = $this->getLink('canonical');
        if ($link) {
            return $link['url'];
        }

        return '';
    }

    public function setCanonicalUrl($url) {

        if (!$url) {
            return $this->removeCanonicalUrl();
        }
        $this->removeLink('canonical');

        $url = $this->u($url);

        return $this->addLink('canonical', $url);
    }

    public function removeCanonicalUrl() {
        return $this->removeLink('canonical');
    }

    public function isImageToolbarVisible() {
        $visible = $this->getMeta('imagetoolbar', 'yes');
        return strcasecmp('no', $visible) === 0;
    }

    public function setImageToolbarVisible($visible) {

        if ($visible) {
            $this->removeMeta('imagetoolbar');
        } else {
            $this->setMeta('imagetoolbar', 'no');
        }

        return $this;
    }

    /**
     * Sets the favicon for the page.
     */
    public function setFavicon($icon) {

        $this->removeLink('shortcut icon');

        if (!$icon) {
            return $this;
        }

        $ext = 'ico';

        if (preg_match('/\.([a-z0-9_-]+)$/i', $icon, $m)) {
            $ext = strtolower($m[1]);
        }


        return $this->addLink('shortcut icon', $this->u($icon), self::getImageMimeTypeForExt($ext));
    }

    public function getFavicon() {

        $link = $this->getLink('shortcut icon');
        if ($link) return $link['url'];

        return false;
    }

    private static function getImageMimeTypeForExt($ext) {
        switch($ext) {

            case 'jpeg':
            case 'jpg':
                return 'image/jpeg';

            case 'gif':
                return 'image/gif';

            case 'png':
                return 'image/png';

            case 'ico':
                return 'image/vnd.microsoft.icon';

            default:
                return '';

        }
    }

    /**
     * Adds a &lt;link&gt; to the header.
     */
    public function addLink($rel, $url, $type = null, $attributes = array()) {

        $priority = 0;

        if (is_numeric($type)) {
            $priority = $type;
            $type = null;
        } else if (is_numeric($attributes)) {
            $priority = $attributes;
            $attributes = array();
        }

        if (is_array($type)) {
            $attributes = array_merge($type, $attributes);
            $type = null;
        }

        if (isset($attributes['priority'])) {
            $priority = $attributes['priority'];
            unset($attributes['priority']);
        }

        if (isset($attributes['type'])) {
            $type = $attributes['type'];
            unset($attributes['type']);
        }

        $url = $this->u($url);
        $index = self::counter();

        $link = compact('rel', 'type', 'url', 'attributes', 'priority', 'index');

        $this->links[] = $link;

        return $this;
    }

    /**
     * @return Array all links w/ the given relation.
     */
    public function &getLinks($rel) {

        $result = array();

        foreach($this->links as $link) {

            if (strcasecmp($link['rel'], $rel) === 0) {
                unset($link['index']);
                $result[] = $link;
            }

        }

        return $result;
    }

    /**
     * @return The first link w/ the given relation, or false if none exists.
     */
    public function getLink($rel) {

        $links = $this->getLinks($rel);
        if ($links) return array_shift($links);

        return false;
    }

    /**
     * Removes all links with the given relation from the page.
     */
    public function removeLink($rel) {

        foreach($this->links as $key => $link) {
            if (strcasecmp($link['rel'], $rel) === 0) {
                unset($this->links[$key]);
            }
        }

        return $this;
    }

    public function renderLinks($return = false) {

        if (empty($this->links)) {
            return $return ? '' : $this;
        }

        uasort($this->links, array('Octopus_Html_Page', 'comparePriorities'));

        $html = '';
        foreach($this->links as $info) {

            $link = new Octopus_Html_Element('link');
            $link->href = $info['url'];
            $link->type = $info['type'];
            $link->rel = $info['rel'];
            $link->setAttributes($info['attributes']);

            $html .= $link->render(true);

        }

        if ($return) {
            return $html;
        }

        echo $html;
        return $this;
    }

    /**
     * Adds a single script file to this page.
     */
    public function addJavascript($url, $attributes = array()) {

        if (is_numeric($attributes)) {
            $priority = $attributes;
            $attributes = array();
        } else {
            $priority = isset($attributes['priority']) ? $attributes['priority'] : 0;
            unset($attributes['priority']);
        }

        $url = $this->u($url);
        $index = self::counter();

        $this->scripts[$url] = compact('url', 'attributes', 'priority', 'index');

        return $this;
    }

    public function addLiteralJavascript($content, $attributes = array()) {

        $content = str_replace('<!--', '', $content);
        $content = str_replace('-->', '', $content);
        $content = trim(strip_tags($content));

        if (!$content) {
            return $this;
        }

        $priority = 0;

        if (is_numeric($attributes)) {
            $priority = $attributes;
            $attributes = array();
        } else if (isset($attributes['priority'])) {
            $priority = $attributes['priority'];
            unset($attributes['priority']);
        }

        $index = self::counter();

        $this->scripts[] = compact('content', 'options', 'priority', 'index');

        return $this;
    }

    /**
     * @return Array of all javascript files added to this page.
     */
    public function &getJavascriptFiles() {

        $result = array();
        foreach($this->scripts as $info) {
            unset($info['index']);
            $result[$info['url']] = $info;
        }

        return $result;
    }

    /**
     * Outputs the HTML for the javascript section.
     */
    public function renderJavascript($return = false) {

        if (empty($this->scripts)) {
            return $return ? '' : $this;
        }

        $html = '';

        uasort($this->scripts, array('Octopus_Html_Page', 'comparePriorities'));

        foreach($this->scripts as $info) {

            $el = new Octopus_Html_Element('script');
            $el->type = 'text/javascript';

            if (isset($info['url'])) {
                $el->src = $info['url'];
            } else {
                $el->text($info['content']);
            }

            $html .= $el->render(true);
        }

        if ($return) {
            return $html;
        }

        echo $html;
        return $this;
    }

    /**
     * Add a CSS file.
     * @param $url String The file to add. URL_BASE gets prepended
     * automatically.
     * @param $options Mixed Options for this file <b>or</b> the media
     * type (e.g. 'screen') <b>or</b> a priority (higher priority = included
     * first).
     *
     */
    public function addCss($url, $attributes = array()) {

        $priority = 0;

        if (is_numeric($attributes)) {
            $priority = $attributes;
            $attributes = array();
        } else if (is_string($attributes)) {
            $attributes = array('media' => $attributes);
        }

        if (isset($attributes['priority'])) {
            $priority = $attributes['priority'];
            unset($attributes['priority']);
        }

        if (!isset($attributes['media'])) {
            $attributes['media'] = 'all';
        }

        $ie = false;
        if (isset($attributes['ie'])) {
            $ie = $attributes['ie'];
            unset($attributes['ie']);
        }

        $url = $this->u($url);
        $index = self::counter();

        $info = compact('url', 'attributes', 'priority', 'index');
        if ($ie) $info['ie'] = $ie;

        $this->css[$url] = $info;

        return $this;
    }

    public function addLiteralCss($content, $attributes = array()) {

        $content = str_replace('<!--', '', $content);
        $content = str_replace('-->', '', $content);
        $content = trim(strip_tags($content));

        if (!$content) {
            return $this;
        }

        $priority = 0;

        if (is_numeric($attributes)) {
            $priority = $attributes;
            $attributes = array();
        } else if (isset($attributes['priority'])) {
            $priority = $attributes['priority'];
            unset($attributes['priority']);
        }

        $index = self::counter();

        $this->css[] = compact('content', 'attributes', 'priority', 'index');

        return $this;
    }

    /**
     * Removes a CSS file.
     */
    public function removeCss($url) {

        $url = $this->u($url);
        unset($this->css[$url]);

        return $this;
    }

    public function getCssFiles() {

        $result = array();

        foreach($this->css as $url => $info) {
            unset($info['index']);
            $result[$url] = $info;
        }

        return $result;
    }

    public function getCssFile($url) {

        $url = $this->u($url);

        if (isset($this->css[$url])) {
            $file = $this->css[$url];
            unset($file['index']);
            return $file;
        }

        return false;
    }

    /**
     * Renders the section containing all CSS links.
     */
    public function renderCss($return = false) {

        if (empty($this->css)) {
            return $return ? '' : $this;
        }

        $html = '';

        uasort($this->css, array('Octopus_Html_Page', 'comparePrioritiesReverse'));

        foreach($this->css as $url => $info) {

            if (isset($info['content'])) {
                $el = new Octopus_Html_Element('style');
                $el->type = 'text/css';
                $el->text($info['content']);
            } else {
                $el = new Octopus_Html_Element('link');
                $el->href = $info['url'];
                $el->type = "text/css";
                $el->rel = "stylesheet";
            }
            $el->setAttributes($info['attributes']);

            $html .=
                self::getOpenConditionalComment($info) .
                $el->render(true) .
                self::getCloseConditionalComment($info);
        }

        if ($return) {
            return $html;
        }

        echo $html;
        return $this;
    }

    private static function getOpenConditionalComment($info) {
        if (!isset($info['ie'])) {
            return '';
        }

        if ($info['ie'] === true) {
            $expr = 'IE';
        } else {
            $expr = $info['ie'];
            $expr = str_replace('<=', 'lte', $expr);
            $expr = str_replace('<', 'lt', $expr);
            $expr = str_replace('>=', 'gte', $expr);
            $expr = str_replace('>', 'gt', $expr);

            if (preg_match('/^\s*(.*?)\s*(\d+)\s*$/', $expr, $m)) {
                $expr = $m[1] . ' IE' . ($m[2] ? ' ' . $m[2] : '');
            }
        }

        return <<<END

<!--[if $expr]>
END;

    }

    private static function getCloseConditionalComment($info) {
        if (!isset($info['ie'])) {
            return '';
        }

        return <<<END
<![endif]-->
END;
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
            return $return ? '' : $this;
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
        return $this;
    }

    private static function comparePriorities($x, $y) {

        $xPriority = isset($x['priority']) ? $x['priority'] : 0;
        $yPriority = isset($y['priority']) ? $y['priority'] : 0;

        $result = $yPriority - $xPriority;

        if ($result !== 0) {
            return $result;
        }

        $xIndex = isset($x['index']) ? $x['index'] : 0;
        $yIndex = isset($y['index']) ? $y['index'] : 0;

        return $xIndex - $yIndex;
    }

    private static function comparePrioritiesReverse($x, $y) {

        $xPriority = isset($x['priority']) ? $x['priority'] : 0;
        $yPriority = isset($y['priority']) ? $y['priority'] : 0;

        $result = $xPriority - $yPriority;

        if ($result !== 0) {
            return $result;
        }

        $xIndex = isset($x['index']) ? $x['index'] : 0;
        $yIndex = isset($y['index']) ? $y['index'] : 0;

        return $xIndex - $yIndex;
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
