<?php

class Octopus_Html_Page {

    private static $instance = null;
    private static $counter = 0;

    public static $defaults = array(

        /**
         * String used to separate elements in a "full" page title.
         * TODO: allow using a function?
         */
        'titleSeparator' => ' | ',

        /**
         * Minifiers to use.
         */
        'minify' => array(
            'javascript' => array(),
            'css' => array()
        ),

        /**
         * Custom function to use to find the physical file associated with
         * a path.  Gets called with 3 args:
         *  - Path to find
         *  - Options array from the Html_Page instance
         * The default file finder searches in SITE_DIR, OCTOPUS_DIR, and
         * ROOT_DIR.
         *  - The Html_Page instance
         */
        'fileFinder' => null,

        /**
         * Custom function to use to convert physical paths into URLs, if any.
         * Gets called with 3 args:
         *  - The file to convert into a URL
         *  - The options array used by the Html_Page instance (which will
         *    contain ROOT_DIR, URL_BASE, etc...)
         *  - The Html_Page instance
         */
        'urlifier' => null

    );

    protected $options;

    private $dirs = array();

    private $scriptDirs = array();
    private $cssDirs = array();

    protected $css = array();
    protected $scripts = array();
    protected $vars = array();
    protected $meta = array();
    protected $links = array();

    private $javascriptAliaser = null;
    private $cssAliaser = null;
    private $minifiers = array();

    protected $fullTitle = null;
    protected $title = null;
    protected $subtitles = array();
    protected $titleSeparator = ' | ';
    protected $breadcrumbs = array();

    public function __construct($options = array()) {

        $this->options = array_merge(self::$defaults, $options);

        foreach($this->options['minify'] as $kind => $minifiers) {

            if (!$minifiers) {
                continue;
            }

            if (!is_array($minifiers)) {
                $minifiers = array($minifiers);
            }

            foreach($minifiers as $m) {
                $this->addMinifier($kind, $m);
            }

        }

        $this->reset();
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

        throw new Octopus_Exception_MethodMissing($this, $name, $args);
    }

    private static function counter() {
        return self::$counter++;
    }

    /**
     * Resets this page to its original state. Removes everything that has
     * been added to it.
     */
    public function reset() {

        $this->dirs = array();

        foreach(array('ROOT_DIR', 'OCTOPUS_DIR', 'SITE_DIR', 'URL_BASE') as $opt) {
            if (!empty($this->options[$opt])) {
                $this->dirs[$opt] = end_in('/', $this->options[$opt]);
            } else {
                $this->dirs[$opt] = get_option($opt, '');
            }
        }

        $this->scriptDirs = array();
        $this->cssDirs = array();

        $this->css = array();
        $this->scripts = array();
        $this->vars = array();
        $this->meta = array();
        $this->links = array();

        $this->fullTitle = null;
        $this->title = null;
        $this->subtitles = array();
        $this->titleSeparator = ' | ';
        $this->breadcrumbs = array();

        $this->addJavascriptDir($this->dirs['ROOT_DIR'], 0);
        $this->addJavascriptDir($this->dirs['SITE_DIR'], 0);
        $this->addJavascriptDir($this->dirs['OCTOPUS_DIR'], PHP_INT_MAX);

        $this->addCssDir($this->dirs['ROOT_DIR'], 0);
        $this->addCssDir($this->dirs['SITE_DIR'], 0);
        $this->addCssDir($this->dirs['OCTOPUS_DIR'], PHP_INT_MAX);

        $this->setMeta('Content-type', 'text/html; charset=UTF-8');
    }

    public function combineJavascript() {
        return $this->addMinifier('javascript', 'Octopus_Minify_Strategy_Combine');
    }

    public function combineCss() {
        return $this->addMinifier('css', 'Octopus_Minify_Strategy_Combine');
    }

    /**
     * @param $type Mixed Either a minifier instance or a class name.
     */
    public function addJavascriptMinifier($type) {
        return $this->addMinifier('javascript', $type);
    }

    /**
     * @param $type Mixed Either a minifier instance or a class name.
     */
    public function addCssMinifier($type) {
        return $this->addMinifier('css', $type);
    }

    /**
     * Clears any existing javascript minfiers and adds the given one.
     */
    public function setJavascriptMinifier($type) {
        return $this->setMinfier('javascript', $type);
    }

    public function setCssMinifier($type) {
        return $this->setMinfier('css', $type);
    }

    private function setMinfier($fileType, $type) {

        $this->minifiers[$fileType] = array();
        $this->addMinifier($fileType, $type);

        $aliaserVar = $fileType . 'Aliaser';
        if ($this->$aliaserVar) {
            $this->minifiers[$fileType][] = $this->$aliaserVar;
        }

        return $this;
    }

    private function addMinifier($fileType, $type) {

        $minifier = null;

        if (is_string($type)) {

            if (class_exists($type)) {
                $minifier = new $type();
            } else {
                $type = 'Octopus_Minify_Strategy_' . camel_case($type, true);
            }

            $minifier = new $type();

        } else {
            $minifier = $type;
        }

        if (!$minifier instanceof Octopus_Minify_Strategy) {
            throw new Octopus_Exception("Minifier must implement Octopus_Minify_Strategy");
        }

        $this->minifiers[$fileType][] = $minifier;

        return $this;
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
    public function addJavascript($file, $section = null, $weight = null, $attributes = array()) {
        return $this->internalAddJavascript('file', $file, $section, $weight, $attributes);
    }

    /**
     * When all files in $files are added to the page (in the order they appear in $files),
     * replace the include with the file specified in $alias.
     */
    public function addJavascriptAlias($files, $alias) {

        if (!$this->javascriptAliaser) {
            $this->javascriptAliaser = new Octopus_Minify_Strategy_Alias(array($this, 'findJavascriptFile'));
            $this->addJavascriptMinifier($this->javascriptAliaser);
        }

        $files = array_map(array($this, 'getJavascriptPhysicalPathAllowMissing'), $files);

        $this->javascriptAliaser->addAlias($files, $alias);

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

        /*
         * Resolve the various ways of calling addJavascript, e.g.:
         *  ->addJavascript($file)
         *  ->addJavascript($file, $weight)
         *  ->addJavascript($file, $section)
         *  ->addJavascript($file, $section, $weight)
         *  ->addJavascript($file, $section, $weight, $attributes)
         */

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

        // index is used to help sort items with the same weight - items added
        // first get sorted before those added later
        $index = self::counter();

        if ($type == 'literal') {
            $file = false;
            $this->scripts[] = compact('file', 'content', 'attributes', 'section', 'weight', 'index');
        } else {

            $file = trim($content);

            if (isset($this->scripts[$file])) {

                // Selectively overwrite stuff
                $js =& $this->scripts[$file];
                $js['attributes'] = array_merge($js['attributes'], $attributes);
                $js['section'] = $section ? $section : $js['section'];
                $js['weight'] = $weight === null ? $js['weight'] : $weight;


            } else {
                if (!$weight) $weight = 0;
                $this->scripts[$file] = compact('file', 'attributes', 'section', 'weight', 'index');
            }
        }

        return $this;
    }

    /**
      * Adds a directory to be searched for Javascript files.
     */
    public function addJavascriptDir($dir, $weight = 0) {

        $this->scriptDirs[] = array(
            'path' => end_in('/', $dir),
            'weight' => $weight
        );

        usort($this->scriptDirs, array('Octopus_Html_Page', 'compareWeights'));

        return $this;
    }

    /**
     * @return Array Directories to be searched for js files added via
     * addJavascriptDir()
     */
    public function getJavascriptDirs() {
        $result = array();
        foreach($this->scriptDirs as $d) {
            $result[] = $d['path'];
        }
        return $result;
    }

    /**
     * Removes a custom javascript directory added via addJavascriptDir().
     */
    public function removeJavascriptDir($dir) {
        foreach($this->scriptDirs as $index => $scriptDir) {
            if ($scriptDir['path'] == $dir) {
                unset($this->scriptDirs[$index]);
            }
        }
    }

    /**
     * Looks through all the directories registered with addCssDir() for $file.
     * @param String $file File to locate.
     * @return Mixed The full physical path to $file if found, otherwise
     * false.
     */
    public function findCssFile($file) {
        return $this->getPhysicalPath($file, $this->cssDirs);
    }

    /**
     * Looks through all the directories registered with addJavascriptDir() for $file.
     * @param String $file File to locate.
     * @return Mixed The full physical path to $file if found, otherwise
     * false.
     */
    public function findJavascriptFile($file) {
        return $this->getPhysicalPath($file, $this->scriptDirs);
    }

    /**
     * @return Mixed Full physical path to the given file, or false if it does
     * not exist.
     */
    private function getPhysicalPath($file, Array $dirs, $useFileFinder = true, $ifMissing = false) {

        if ($useFileFinder && is_callable($this->options['fileFinder'])) {
            $found = call_user_func($this->options['fileFinder'], $file, $this->options, $this);
            return $this->checkFile($found);
        }

        if (!$file) {
            return false;
        }
        if (preg_match('#^(https?:)?//#i', $file)) {
            return $file;
        }

        if ($file[0] !== '/' || is_file($file)) {
            // Relative paths get sent through unaltered
            return $file;
        }

        foreach($dirs as $dir) {

            $full = $dir['path'] . ltrim($file, '/');

            if (is_file($full)) {
                return $full;
            }

        }

        return $ifMissing;
    }

    private function getJavascriptPhysicalPathAllowMissing($file) {
        return $this->getPhysicalPathAllowMissing($file, $this->scriptDirs);
    }

    private function getPhysicalPathAllowMissing($file, Array $dirs) {
        $result = $this->getPhysicalPath($file, $dirs, true, false);
        if ($result !== false) return $result;
        return $this->dirs['ROOT_DIR'] . ltrim($file, '/');
    }

    public function getUrlForFile($file, $useUrlifier = true) {

        if ($useUrlifier && is_callable($this->options['urlifier'])) {
            return call_user_func($this->options['urlifier'], $file, $this->options, $this);
        }

        if (preg_match('#^(https?:)?//#i', $file)) {
            return $file;
        }

        if ($file[0] !== '/') {
            // this is a relative path
            return $file;
        }

        $mtime = '';
        if (is_file($file)) {
            $mtime = @filemtime($file);
            $mtime = $mtime ? "?$mtime" : '';
        }

        $root = $this->dirs['ROOT_DIR'];

        if (starts_with($file, $root)) {
            $file = substr($file, strlen($root));
            $file = start_in('/', $file);
        }

        // HACK: When installed locally, SoleCMS defines ROOT_DIR as
        // /whatever/core/ and SITE_DIR as /whatever/sites/site, not
        // /whatever/core/sites/site, so stripping ROOT_DIR off SITE_DIR
        // fails.
        if (defined('SG_VERSION')) {
            $root = preg_replace('#/core/$#', '/', $root, -1, $count);
            if ($count > 0 && starts_with($file, $root, false, $remainder)) {
                return $this->dirs['URL_BASE'] . $remainder . $mtime;
            }
        }

        // Fall back to just URL_BASE/file
        return $this->u($file) . $mtime;
    }

    /**
     * @return Array of all javascript files added to this page.
     */
    public function &getJavascriptFiles($section = '', $minify = true) {

        if (is_bool($section)) {
            $minify = $section;
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

        // For local JS files, figure out where they are
        foreach($scripts as &$script) {

            if (!$script['file']) {
                continue;
            }

            $script['file'] = $this->getPhysicalPathAllowMissing($script['file'], $this->scriptDirs);

        }

        usort($scripts, array('Octopus_Html_Page', 'compareWeights'));

        if ($minify) {
            $scripts = $this->minify('javascript', $scripts);
        }

        foreach($scripts as &$item) {
            $item['file'] = $this->getUrlForFile($item['file']);
        }

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

        $scripts = $this->getJavascriptFiles($section, $useAliases);
        $html = $includeVars ? $this->renderJavascriptVars(true) : '';

        foreach($scripts as $info) {

            $el = new Octopus_Html_Element('script');
            $el->type = 'text/javascript';

            if (!empty($info['content'])) {
                // Literal JS
                $el->text("\n{$info['content']}\n");
            } else if (!empty($info['file'])) {
                $el->src = $info['file'];
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
     * @param $file String The file to add. URL_BASE gets prepended
     * automatically.
     * @param $options Mixed Options for this file <b>or</b> the media
     * type (e.g. 'screen') <b>or</b> a weight (higher weight = included
     * first).
     *
     */
    public function addCss($file, $weight = null, $attributes = array()) {

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

        $index = self::counter();

        $info = compact('file', 'attributes', 'weight', 'index');
        if ($ie) $info['ie'] = $ie;

        $this->css[$file] = $info;

        return $this;
    }

    public function addCssAlias($urls, $alias) {

        if (!$this->cssAliaser) {
            $this->cssAliaser = new Octopus_Minify_Strategy_Alias(array($this, 'findCssFile'));
            $this->addCssMinifier($this->cssAliaser);
        }

        $this->cssAliaser->addAlias($urls, $alias);

        return $this;
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
        $file = false;

        $this->css[] = compact('file', 'content', 'attributes', 'weight', 'index');

        return $this;
    }

    /**
     * Adds a directory to be searched for CSS files.
     */
    public function addCssDir($dir, $weight = 0) {

        $this->cssDirs[] = array(
            'path' => end_in('/', $dir),
            'weight' => $weight
        );
        usort($this->cssDirs, array('Octopus_Html_Page', 'compareWeights'));

        return $this;
    }

    /**
     * @return Array Directories to be searched for CSS files added via
     * addCssDir()
     */
    public function getCssDirs() {
        $result = array();
        foreach($this->cssDirs as $d) {
            $result[] = $d['path'];
        }
        return $result;
    }

    /**
     * Removes a custom css directory added via addCssDir().
     */
    public function removeCssDir($dir) {
        foreach($this->cssDirs as $index => $cssDir) {
            if ($cssDir['path'] == $dir) {
                unset($this->cssDirs[$index]);
            }
        }
    }

    /**
     * Removes a CSS file.
     */
    public function removeCss($file) {

        unset($this->css[$file]);

        return $this;
    }

    public function &getCssFiles($minify = true) {

        $css = $this->css;
        usort($css, array('Octopus_Html_Page', 'compareWeights'));

        foreach($css as &$item) {

            if (empty($item['file'])) {
                continue;
            }

            $item['file'] = $this->getPhysicalPathAllowMissing($item['file'], $this->cssDirs);
        }

        $css = $this->minify('css', $css);


        foreach($css as &$item) {
            $item['file'] = $this->getUrlForFile($item['file']);
        }

        return $css;
    }

    public function getCssFile($file) {

        if (isset($this->css[$file])) {
            $file = $this->css[$file];
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
                $el->href = $info['file'];
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
        return u($url, null, array('URL_BASE' => $this->dirs['URL_BASE']));
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

        $html = '<title>' . h($this->getFullTitle()) . '</title>';

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
     * Given an array of items (script or css files), applies all
     * minification strategies that have been configured for the file type.
     */
    protected function &minify($type, $items) {

        if (empty($this->minifiers[$type])) {
            return $items;
        }

        $minifiers = $this->minifiers[$type];

        foreach($minifiers as $m) {

            self::indexItemsByUrl($items, $itemsByUrl, $noUrlItems);
            $minified = $m->minify(array_keys($itemsByUrl));

            if (!$minified) {
                // Minifier did not do anything
                continue;
            }

            // Re-integrate minified stuff into the original $items array

            // First, normalize the $itemsByUrl array so that unused items
            // are removed from it.
            foreach($minified as $minifiedUrl => $oldUrls) {

            	// Find the min weight for the combined item
                $minWeight = null;
                foreach($oldUrls as $old) {
                	if (!isset($itemsByUrl[$old])) {
                		continue;
                	}
                	$item = $itemsByUrl[$old];
                	if ($minWeight === null || $item['weight'] < $minWeight) {
                		$minWeight = $item['weight'];
                	}
                }

                $oldUrl = array_shift($oldUrls);

                $item =& $itemsByUrl[$oldUrl];
                $item['file'] = $minifiedUrl;
                $item['old_url'] = $oldUrl;
                $item['weight'] = $minWeight;
                unset($item);

                foreach($oldUrls as $old) {
                    unset($itemsByUrl[$old]);
                }
            }

            // Recombine the $itemsByUrl and $noUrlItems arrays
            $items = array_values($itemsByUrl);
            if ($noUrlItems) {
                foreach($noUrlItems as $item) {
                    $items[] = $item;
                 }
            }

            usort($items, array('Octopus_Html_Page', 'compareWeights'));
        }
         return $items;
    }

    private static function indexItemsByUrl(&$items, &$itemsByUrl, &$noUrlItems) {

        $itemsByUrl = array();
        $noUrlItems = array();

        foreach($items as $item) {

            if (!empty($item['file'])) {
                $itemsByUrl[$item['file']] = $item;
            } else {
                $noUrlItems[] = $item;
            }

        }

    }

    public static function singleton() {
        if (!self::$instance) {
            self::$instance = new Octopus_Html_Page();
        }
        return self::$instance;
    }


}


?>
