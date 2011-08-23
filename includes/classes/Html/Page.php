<?php

Octopus::loadClass('Octopus_Html_Element');

class Octopus_Html_Page {

    private static $instance = null;
    private static $counter = 0;

    public static $defaults = array(
        'titleSeparator' => ' | ',
        'minifier' => array('src')
    );

    protected $options;

    protected $css = array();
    protected $scripts = array();
    protected $vars = array();
    protected $meta = array();
    protected $links = array();

    protected $scriptAliases = array();
    protected $cssAliases = array();

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

    public function __call($name, $args) {

        // Map, e.g., addBottomJavascript('file.js') to addJavascript('file.js', 'bottom');
        if (preg_match('/^add([a-zA-Z0-9_]+)Javascript$/', $name, $m)) {
            $fullArgs = array();
            $fullArgs[] = array_shift($args);
            $fullArgs[] = underscore($m[1]);
            while($args) {
                $fullArgs[] = array_shift($args);
            }
            return call_user_func_array(array($this, 'addJavascript'), $fullArgs);
        }

        throw new Octopus_Exception("Cannot call $name on Octopus_Html_Page");
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

        $weight = 0;

        if (is_numeric($type)) {
            $weight = $type;
            $type = null;
        } else if (is_numeric($attributes)) {
            $weight = $attributes;
            $attributes = array();
        }

        if (is_array($type)) {
            $attributes = array_merge($type, $attributes);
            $type = null;
        }

        if (isset($attributes['weight'])) {
            $weight = $attributes['weight'];
            unset($attributes['weight']);
        }

        if (isset($attributes['type'])) {
            $type = $attributes['type'];
            unset($attributes['type']);
        }

        $url = $this->u($url);
        $index = self::counter();

        $link = compact('rel', 'type', 'url', 'attributes', 'weight', 'index');

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

        uasort($this->links, array('Octopus_Html_Page', 'compareWeights'));

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
    public function addJavascript($url, $section = null, $weight = null, $attributes = array()) {

        return $this->internalAddJavascript('url', $this->u($url), $section, $weight, $attributes);
    }

    /**
     * When all files in $files are added to the page (in the order they appear in $files),
     * replace the include with the file specified in $alias.
     */
    public function addJavascriptAlias($urls, $alias, $attributes = array()) {

        $index = self::counter();
        $urls = array_map(array($this, 'u'), $urls);
        $url = $this->u($alias);

        $this->scriptAliases[] = compact('urls', 'url', 'attributes', 'index');
        return $this;
    }

    public function addLiteralJavascript($content, $section = null, $weight = null, $attributes = array()) {

        $content = str_replace('<!--', '', $content);
        $content = str_replace('-->', '', $content);
        $content = trim(strip_tags($content));

        if (!$content) {
            return $this;
        }

        return $this->internalAddJavascript('literal', $content, $section, $weight, $attributes);
    }

    private function internalAddJavascript($type, $content, $section, $weight, $attributes) {

        if ($weight === null && is_numeric($section)) {
            $weight = $section;
            $section = null;
        }

        if (is_array($section)) {
            $attributes = array_merge($section, $attributes);
            $section = null;
        }

        if (is_array($weight)) {
            $attributes = array_merge($weight, $attributes);
            $weight = null;
        }

        if (isset($attributes['weight'])) {
            $weight = isset($attributes['weight']) ? $attributes['weight'] : 0;
            unset($attributes['weight']);
        }

        if (isset($attributes['section'])) {
            $section = $attributes['section'];
            unset($attributes['section']);
        }

        if (!$section) {
            $section = '';
        }

        if (!$weight) {
            $weight = 0;
        }

        $index = self::counter();

        if ($type == 'literal') {
            $this->scripts[] = compact('content', 'attributes', 'section', 'weight', 'index');
        } else {
            $url = $content;
            $this->scripts[$url] = compact('url', 'attributes', 'section', 'weight', 'index');
        }

        return $this;
    }

    /**
     * @return Array of all javascript files added to this page.
     */
    public function &getJavascriptFiles($section = '', $useAliases = true) {

        if (is_bool($section)) {
            $useAliases = $section;
            $section = '';
        }

        if ($section !== null) {
            $scripts = array();
            foreach($this->scripts as $s) {
                if ($s['section'] == $section) {
                    $scripts[] = $s;
                }
            }
        } else {
            $scripts = $this->scripts;
        }

        usort($scripts, array('Octopus_Html_Page', 'compareWeights'));

        if ($useAliases) {
            $scripts = $this->processAliases($scripts, $this->scriptAliases);
        }

        $scripts = $this->minify($scripts);

        return $scripts;
    }

    /**
     * Outputs the HTML for the javascript section.
     */
    public function renderJavascript($section = null, $return = false, $useAliases = true, $includeVars = true) {

        if (is_bool($section)) {
            $return = $section;
            $section = '';
        }

        if (empty($this->scripts)) {
            return $return ? '' : $this;
        }

        $scripts = $this->getJavascriptFiles($section, $useAliases);

        $html = $includeVars ? $this->renderJavascriptVars(true) : '';

        foreach($scripts as $info) {

            $el = new Octopus_Html_Element('script');
            $el->type = 'text/javascript';

            if (isset($info['url'])) {
                $el->src = $info['url'];
            } else {
                $el->text("\n{$info['content']}\n");
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
     * type (e.g. 'screen') <b>or</b> a weight (higher weight = included
     * first).
     *
     */
    public function addCss($url, $weight = null, $attributes = array()) {

        if (is_string($attributes)) {
            $attributes = array('media' => $attributes);
        }

        if (is_string($weight)) {
            $attributes['media'] = $weight;
            $weight = null;
        }

        if (is_array($weight)) {
            $attributes = array_merge($attributes, $weight);
            $weight = null;
        }

        if (isset($attributes['weight'])) {
            $weight = $attributes['weight'];
            unset($attributes['weight']);
        }

        if (!isset($attributes['media'])) {
            $attributes['media'] = 'all';
        }

        $ie = false;
        if (isset($attributes['ie'])) {
            $ie = $attributes['ie'];
            unset($attributes['ie']);
        }

        if (!$weight) {
            $weight = 0;
        }

        $url = $this->u($url);
        $index = self::counter();

        $info = compact('url', 'attributes', 'weight', 'index');
        if ($ie) $info['ie'] = $ie;

        $this->css[$url] = $info;

        return $this;
    }

    public function addCssAlias($urls, $alias, $attributes = array()) {
        $urls = array_map(array($this, 'u'), $urls);
        $url = $this->u($alias);
        $index = self::counter();
        $this->cssAliases[] = compact('urls', 'url', 'attributes', 'index');
    }

    public function addLiteralCss($content, $attributes = array()) {

        $content = str_replace('<!--', '', $content);
        $content = str_replace('-->', '', $content);
        $content = trim(strip_tags($content));

        if (!$content) {
            return $this;
        }

        $weight = 0;

        if (is_numeric($attributes)) {
            $weight = $attributes;
            $attributes = array();
        } else if (isset($attributes['weight'])) {
            $weight = $attributes['weight'];
            unset($attributes['weight']);
        }

        $index = self::counter();

        $this->css[] = compact('content', 'attributes', 'weight', 'index');

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

    public function &getCssFiles($useAliases = true) {

        $css = $this->css;
        usort($css, array('Octopus_Html_Page', 'compareWeights'));

        if ($useAliases) {
            $css = $this->processAliases($css, $this->cssAliases);
        }

        return $css;
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
    public function renderCss($return = false, $useAliases = true) {

        if (empty($this->css)) {
            return $return ? '' : $this;
        }

        $html = '';
        $css = $this->getCssFiles($useAliases);

        foreach($css as $info) {

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
     * @param $weight Order in which variable should be set. Higher
     * weight = render sooner.
     */
    public function setJavascriptVar($name, $value, $weight = 0) {
        $this->vars[$name] = array('value' => $value, 'weight' => $weight);
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

        uasort($this->vars, array('Octopus_Html_Page', 'compareWeights'));

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

    public function renderTitle($return = false) {
        
        $html = '<title>' . $this->getFullTitle() . '</title>';

        if ($return) {
            return $html;
        }

        echo $html;
        return $this;
    }

    /**
     * Renders the entire <head> of the page.
     */
    public function renderHead($return = false, $includeTag = true, $useAliases = true) {
        
        if ($return) {
            
            $html = ($includeTag ? '<head>' : '');

            $html .= $this->renderTitle(true);

            $html .= $this->renderMeta(true);

            $html .= $this->renderCss(true, $useAliases);

            $html .= $this->renderLinks(true);

            $html .= $this->renderJavascript(true, $useAliases);

            $html .= ($includeTag ? '</head>' : '');

            return $html;
        }

        echo "\n<head>\n";

        $this->renderTitle();
        echo "\n";

        $this->renderMeta();
        echo "\n";

        $this->renderCss(false, $useAliases);
        echo "\n";

        $this->renderLinks();
        echo "\n";

        $this->renderJavascript(false, $useAliases);

        echo "\n</head>\n";
        return $this;
    }

    private static function compareAliases($x, $y) {
        
        $result = count($y['urls']) - count($x['urls']);
        if ($result !== 0) {
            return $result;
        }

        return $x['index'] - $y['index'];

    }

    private static function compareWeights($x, $y) {

        $xweight = isset($x['weight']) ? $x['weight'] : 0;
        $yweight = isset($y['weight']) ? $y['weight'] : 0;

        // Lighter thing sorts first
        $result = $xweight - $yweight;

        if ($result !== 0) {
            return $result;
        }

        // If weight is the same, the one that was added first sorts first
        $xIndex = isset($x['index']) ? $x['index'] : self::counter();
        $yIndex = isset($y['index']) ? $y['index'] : self::counter();

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

    /**
     * @return Array The result of injecting any aliases present in $aliases into
     * $files.
     */
    protected function processAliases($items, $aliases) {
        
        if (empty($aliases)) {
            return $items;
        }

        // Sort aliases in the order they should be attempted
        usort($aliases, array('Octopus_Html_Page', 'compareAliases'));

        // NOTE: At this point, URL_BASE has already been prepended to everything that needs it

        $result = array();

        foreach($aliases as $a) {

            if (self::checkIfAlias($items, $a['urls'], $weight, $index)) {

                // We can use this in place
                $a['weight'] = $weight;
                $a['index'] = $index;
                $a['section'] = '';

                // TODO: Actually compare attributes and section as well when checking if files can be 
                // aliased

                $result[] = $a;

                foreach($a['urls'] as $url) {
                    $key = self::findByUrl($items, $url);
                    if ($key !== false) unset($items[$key]);
                }

            } 

            if (empty($items)) {
                break;
            }
        }

        foreach($items as $item) {
            $result[] = $item;
        }

        usort($result, array('Octopus_Html_Page', 'compareWeights'));

        return $result;
    }

    private static function checkIfAlias($items, $aliasCandidateUrls, &$weight, &$index) {
        
        if (empty($items)) {
            return empty($aliasCandidateUrls);
        }

        $prev = null;
        $weight = 0;
        $index = null;

        foreach($aliasCandidateUrls as $url) {

            $key = self::findByUrl($items, $url, $itemIndex);

            if ($key === false) {
                return false;
            }

            // Urls must appear in the same order specified in the alias
            if ($prev !== null && $itemIndex < $prev) {
                return false;
            }

            $weight += $items[$key]['weight'];

            if ($index === null || $items[$key]['index'] < $index) {
                $index = $items[$key]['index'];
            }

            $prev = $itemIndex;
        }

        return true;
    }

    /**
     * Given an array of items (script or css files), applies the minifier.
     */
    protected function &minify($items) {
         
         if (empty($this->options['minifier'])) {
             return $items;
         }   

         $minifiers = $this->options['minifier'];
         if (!is_array($minifiers)) $minifiers = array($minifiers);

         $itemsByUrl = array();
         $noUrlItems = array();
         foreach($items as $item) {
             if (empty($item['url'])) {
                 $noUrlItems[] = $item;
             } else {
                $itemsByUrl[$item['url']] = $item;
            }
         }

         foreach($minifiers as $class) {
             
             $class = 'Octopus_Minify_Strategy_' . camel_case($class, true);
             Octopus::loadClass($class);

             $strat = new $class();
             $minified = $strat->getMinifiedUrls(array_keys($itemsByUrl));

             if (!$minified) {
                 continue;
             }

             foreach($minified as $url => $oldUrls) {
                 $oldUrl = array_shift($oldUrls);
                 
                 $item =& $itemsByUrl[$oldUrl];
                 $item['old_url'] = $item['url'];
                 $item['url'] = $url;
                 
                 foreach($oldUrls as $old) {
                     unset($itemsByUrl[$old]);
                 }
             }
         }

         $result = array_values($itemsByUrl);

         if ($noUrlItems) {
            // re-incorporate literal js
            foreach($noUrlItems as $item) {
                $result[] = $item;
            }
            usort($result, array('Octopus_Html_Page', 'compareWeights'));
         }

         return $result;
    }

    private static function findByUrl(&$ar, $url, &$index = 0) {
        $index = 0;
        foreach($ar as $key => $item) {
            if (strcasecmp($item['url'], $url) === 0) {
                return $key;
            }
            $index++;
        }
        return false;
    }

    public static function singleton() {
        if (!self::$instance) {
            self::$instance = new Octopus_Html_Page();
        }
        return self::$instance;
    }


}


?>
