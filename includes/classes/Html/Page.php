<?php

/**
 * For convenience in templates, this class implements ArrayAccess. You can
 * access the <head> of a page as `$PAGE.head` and any other named section
 * as `$PAGE.$section`.
 */
class Octopus_Html_Page implements ArrayAccess {

    private $javascriptAliaser = null	;
    private $cssAliaser = null;
    private $minifiers = array();

    private static $instance = null;
    private static $counter = 0;

    public static $defaults = array(

        /**
         * String used to separate elements in a "full" page title.
         */
        'titleSeparator' => ' | ',

        /**
         * Minifiers to use.
         */
        'minify' => array(
            'javascript' => array(),
            'css' => array()
        ),

    );

    protected $options;

    private $sections = array();

    private $vars = array();
    private $meta = array();
    private $links = array();
    private $fullTitle = null;
    private $title = null;
    private $subtitles = array();
    private $titleSeparator = ' | ';
    private $breadcrumbs = array();

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

    public function __get($name) {

    	return $this->getSection($name);

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

        $url = $this->urlify($url);
        $this->breadcrumbs[$url] = $text;
        $this->subtitles[] = $text;

        return $this;
    }

    /**
     * Add a CSS file to the 'head' section.
     * @param String $file The file to add.
     * @param Array|String|Number $options Options for this file <b>or</b> the
     * media type (e.g. 'screen') <b>or</b> a weight.
     * @param Array $attributes
     */
    public function addCss($file, $weight = null, $attributes = array()) {
    	$head = $this->getSection('head');
    	$args = func_get_args();
    	call_user_func_array(array($head, 'addCss'), $args);
    	return $this;
    }

    public function addCssAlias($urls, $alias) {

        if (!$this->cssAliaser) {
            $this->cssAliaser = new Octopus_Minify_Strategy_Alias(array($this, 'findFile'));
            $this->addCssMinifier($this->cssAliaser);
        }

        $this->cssAliaser->addAlias($urls, $alias);

        return $this;
    }

    /**
     * @param $type Mixed Either a minifier instance or a class name.
     */
    public function addCssMinifier($type) {
        return $this->addMinifier('css', $type);
    }

    /**
     * Adds a <script> tag to the page.
     * @param String $file Path to the file to add or a full URL to an
     * externally-hosted script.
     * @param String $section The section of the page to which to add the
     * javascript.
     * @param Number $weight
     * @param Array $attributes
     */
    public function addJavascript($file, $section = 'head', $weight = null, $attributes = array()) {

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
            $section = 'head';
        }

        if (is_array($section)) {
            $attributes = array_merge($section, $attributes);
            $section = 'head';
        }

        if (is_array($weight)) {
            $attributes = array_merge($weight, $attributes);
            $weight = null;
        }

        if (isset($attributes['weight'])) {
            $weight = isset($attributes['weight']) ? $attributes['weight'] : null;
            unset($attributes['weight']);
        }

        if (isset($attributes['section'])) {
            $section = $attributes['section'];
            unset($attributes['section']);
        }

        $section = $this->getSection($section);
        $section->addJavascript($file, $weight, $attributes);

        return $this;
    }

    /**
     * When all files in $files are added to the page (in the order they appear in $files),
     * replace the include with the file specified in $alias.
     */
    public function addJavascriptAlias($files, $alias) {

        if (!$this->javascriptAliaser) {
            $this->javascriptAliaser = new Octopus_Minify_Strategy_Alias(array($this, 'findFile'));
            $this->addJavascriptMinifier($this->javascriptAliaser);
        }

        $files = array_map(array($this, 'findFile'), $files);

        $this->javascriptAliaser->addAlias($files, $alias);

        return $this;
    }

    /**
     * @param $type Mixed Either a minifier instance or a class name.
     */
    public function addJavascriptMinifier($type) {
        return $this->addMinifier('javascript', $type);
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

        $url = $this->urlify($url);
        $index = self::counter();

        $link = compact('rel', 'type', 'url', 'attributes', 'weight', 'index');

        $this->links[] = $link;

        return $this;
    }

    public function combineCss() {
        return $this->addMinifier('css', 'Octopus_Minify_Strategy_Combine');
    }

    public function combineJavascript() {
        return $this->addMinifier('javascript', 'Octopus_Minify_Strategy_Combine');
    }

    /**
     * (Internal method.)
     */
    public static function compareWeights($x, $y) {

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
     * @param String $file A path to resolve
     * @return String If $file can be resolved to a real file, the path to that
     * file is returned. Otherwise, $file is returned unaltered and $found
     * is set to false.
     */
    public function findFile($file, &$found = false) {

    	$found = false;

		$app = Octopus_App::singleton();
		$o =& $this->options;

		// Note that, mostly for testing, ROOT_DIR etc. can be overridden
		// in the $options array passed to page's constructor.

		$dirs = array(
			'root' => 'ROOT_DIR',
			'theme_site' => null,
			'site' => 'SITE_DIR',
			'theme_octopus' => null,
			'octopus' => 'OCTOPUS_DIR',
		);

		foreach($dirs as $key => $constant) {

			if (!$constant) {
				continue;
			}

			if (!empty($o[$key])) {
				$dirs[$key] = $o[$constant];
			} else if ($app) {
				$dirs[$key] = $app->$constant;
			} else {
				$dirs[$key] = null;
			}

		}

		$theme = $app->getTheme();
		if ($theme) {

			if ($dirs['site']) {
				$dirs['theme_site'] = $dirs['site'] . 'themes/' . $theme . '/';
			}

			if ($dirs['octopus']) {
				$dirs['theme_octopus'] = $dirs['octopus'] . 'themes/' . $theme . '/';
			}
		}

		foreach($dirs as $dir) {

			if (!$dir) continue;

			$candidate = $dir . ltrim($file, '/');
			if (is_file($candidate)) {
				$found = true;
				return $candidate;
			}

		}

		return $file;
	}

    public function getBreadcrumbs() {
        return $this->breadcrumbs;
    }

    public function getCanonicalUrl() {

        $link = $this->getLink('canonical');
        if ($link) {
            return $link['url'];
        }

        return '';
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

    /**
     * @param String $section
     * @param  boolean $minify
     * @return Array
     */
    public function getCssFiles($section = 'head', $minify = true) {

    	if (func_num_args() === 1 && is_bool($section)) {
    		$minify = $section;
    		$section = 'head';
    	}

		if (!isset($this->sections[$section])) {
			return array();
		}

		$section = $this->sections[$section];
		return $section->getCssFiles($minify);

    }

    public function getDescription() {
        return $this->getMeta('description');
    }

    public function getFullTitle() {
        if ($this->fullTitle !== null) {
            return $this->fullTitle;
        } else {
            return $this->buildFullTitle();
        }
    }

    /**
     * @return Array of all javascript files added to $section.
     */
    public function getJavascriptFiles($section = 'head', $minify = true) {

    	if (is_bool($section)) {
    		$minify = $section;
    		$section = 'head';
    	}

		if (!isset($this->sections[$section])) {
			return array();
		}

		$section = $this->sections[$section];
		return $section->getJavascriptFiles($minify);

    }


    public function getJavascriptVar($name, $default = null) {
        return isset($this->vars[$name]) ? $this->vars[$name]['value'] : $default;
    }

    /**
     * @return Array The set of defined javascript variables, added using
     * ::setJavascriptVar
     */
    public function getJavascriptVars() {

        $result = array();

        foreach($this->vars as $name => $info) {
            $result[$name] = $info['value'];
        }

        return $result;
    }

    public function getKeywords() {
        return $this->getMeta('keywords');
    }

    /**
     * @return Array All <link> elements (excluding things added via ::addCss)
     */
    public function getLinks($rel = null) {

        $result = array();

        foreach($this->links as $link) {

            unset($link['index']);

            if ($rel === null || strcasecmp($link['rel'], $rel) === 0) {
                $result[] = $link;
            }

        }

        return $result;
    }

    public function getMeta($key, $default = null) {
        $key = strtolower($key);
        return isset($this->meta[$key]) ? $this->meta[$key]['value'] : $default;
    }

    /**
     * @param String $name Case-sensitive
     * @return Octopus_Html_Page_Section The section named $name.
     */
    public function getSection($name, $autoCreate = true) {

    	if (!isset($this->sections[$name])) {

    		// Slight HACK: So that the 'head' section renders a full <head>
    		// element when coerced to a string, we use a special subclass
    		if ($name == 'head') {
    			$this->sections[$name] = new Octopus_Html_Page_Section_Head($name, $this);
    		} else {
	    		$this->sections[$name] = new Octopus_Html_Page_Section($name, $this);
	    	}

    	}

    	return $this->sections[$name];
    }

    /**
     * @return Array Octopus_Html_Page_Section instances representing each of
     * the different logical sections of this page.
     */
    public function getSections() {
    	return $this->sections;
    }

    public function getTitle() {
        return $this->title;
    }

    public function getTitleSeparator() {
        return $this->options['titleSeparator'];
    }

    /**
     * @internal
     * Given an array of items (script or css files), applies all
     * minification strategies that have been configured for the file type.
     * NOTE: This method is public to support Octopus_Html_Page_Section. You
     * probably shouldn't call it directly.
     * @param String $type 'css' or 'javascript'
     * @param Array $items
     */
    public function minify($type, $items) {

        if (empty($this->minifiers[$type])) {
            return $items;
        }

        $minifiers = $this->minifiers[$type];

        // Ensure that $items is indexed by filename
        $itemsByFile = array();
        foreach($items as $item) {
        	$itemsByFile[$item['file']] = $item;
        }
        $items = $itemsByFile;


        foreach($minifiers as $m) {

        	// Pass minifier an array of distinct files
            $minified = $m->minify(array_keys($items));

            if (!$minified) {
                // Minifier did not do anything
                continue;
            }

            // Re-integrate minified stuff into $items
            foreach($minified as $minifiedFile => $oldFiles) {

            	// NOTE: If multiple items with different weights were combined,
            	// use the lowest weight/index combination
                $newItem = null;

                foreach($oldFiles as $old) {

                	if (!isset($items[$old])) {
                		continue;
                	}

                	$item = $items[$old];
                	if (!isset($item['weight'])) $item['weight'] = 0;

                	if ($newItem === null) {
                		$newItem = $item;
                	}
                	unset($items[$old]);

                	if (!isset($newItem['weight'])) {
                		$newItem['weight'] = 0;
                	}

                	if ($item['weight'] < $newItem['weight']) {
                		$newItem['weight'] = $item['weight'];
                	}

                	if ($item['index'] < $newItem['index']) {
                		$newItem['index'] = $item['index'];
                	}

                }

                if ($newItem) {

                	$newItem['unminified_files'] = $oldFiles;
                	$newItem['file'] = $minifiedFile;
                	$items[$newItem['file']] = $newItem;

                }


            }

            usort($items, array(__CLASS__, 'compareWeights'));
        }

        return $items;
    }

    /**
     * @internal
     */
    public function offsetExists($name) {
    	return true;
    }

    /**
     * @param String $name
     * @return Octopus_Html_Page_Section
     * @uses ::getSection()
     */
    public function offsetGet($name) {
    	return $this->$name;
    }

    /**
     * @internal Do not use.
     * @throws Octopus_Exception
     */
    public function offsetSet($name, $value) {
    	throw new Octopus_Exception("offsetSet is not supported on " . __CLASS__);
    }

    /**
     * @internal Do not use.
     * @throws Octopus_Exception
     */
    public function offsetUnset($name) {
    	throw new Octopus_Exception("offsetUnset is not supported on " . __CLASS__);
    }

    public function removeAllBreadcrumbs() {
        $this->breadcrumbs = array();
    }

    public function removeBreadcrumb($url) {
        $url = $this->u($url);
        unset($this->breadcrumbs[$url]);
        return $this;
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

    public function removeMeta($key) {
        return $this->setMeta($key, null);
    }

    /**
     * Renders the css from the 'head' section.
     * @return String|Octopus_Html_Page
     */
	public function renderCss($return = false, $minify = true) {

		$head = $this->getSection('head');

		if ($return) {
			return $head->renderCss(true, $minify);
		} else {
			$head->renderCss(false, $minify);
			return $this;
		}

	}

    /**
     * Renders the entire <head> of the page.
     */
    public function renderHead($return = false, $includeTag = true, $minify = true) {

        if ($return) {

            $html = ($includeTag ? '<head>' : '');

            $html .= $this->renderTitle(true);

            $html .= $this->renderMeta(true);

            $html .= $this->renderCss(true, $minify);

            $html .= $this->renderLinks(true);

            $html .= $this->renderJavascriptVars(true);

            $html .= $this->renderJavascript(true, $minify);

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

    /**
     * Renders javascript added to the given section.
     * @param  boolean $return
     * @param  boolean $minify
     * @return String|Octopus_Html_Page
     */
    public function renderJavascript($section = 'head', $return = false, $minify = true) {

    	if (is_bool($section)) {
    		$minify = func_num_args() === 2 ? $return : $minify;
    		$return = $section;
    		$section = 'head';
    	}

    	if (!isset($this->sections[$section])) {
    		return $return ? '' : $this;
    	}

    	$section = $this->sections[$section];

    	if ($return) {
    		return $section->renderJavascript(true, $minify);
    	} else {
    		$section->renderJavascript(false, $minify);
    		return $this;
    	}

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

    public function renderTitle($return = false) {

        $html = '<title>' . h($this->getFullTitle()) . '</title>';

        if ($return) {
            return $html;
        }

        echo $html;
        return $this;
    }

    /**
     * Resets this page to its original state. Removes everything that has
     * been added to it.
     */
    public function reset() {

    	$this->sections = array();
        $this->vars = array();
        $this->meta = array();
        $this->links = array();

        $this->fullTitle = null;
        $this->title = null;
        $this->subtitles = array();
        $this->titleSeparator = ' | ';
        $this->breadcrumbs = array();

        $this->setMeta('Content-type', 'text/html; charset=UTF-8');
    }

    /**
     * Resets the full title to the default.
     */
    public function resetFullTitle() {
        return $this->setFullTitle(null);
    }

    public function setCanonicalUrl($url) {

        if (!$url) {
            return $this->removeCanonicalUrl();
        }
        $this->removeLink('canonical');

        $url = $this->urlify($url);

        return $this->addLink('canonical', $url);
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

    public function setCssMinifier($type) {
        return $this->setMinifier('css', $type);
    }

    public function setDescription($desc) {
        return $this->setMeta('description', $desc);
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
     * Clears any existing javascript minfiers and adds the given one.
     */
    public function setJavascriptMinifier($type) {
        return $this->setMinfier('javascript', $type);
    }

    /**
     * Sets a global javascript variable.
     * @param $name Name of the variable.
     * @param $value Value for the variable.
     * @param $weight Order in which variable should be set. Lower weights
     * render earlier.
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

    public function setKeywords($keywords) {
        return $this->setMeta('keywords', $keywords);
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

    public function setTitle($title) {
        $this->title = $title;
        return $this;
    }

    public function setTitleSeparator($sep) {
        $this->options['titleSeparator'] = $sep;
        return $this;
    }

    public static function singleton() {
        if (!self::$instance) {
            self::$instance = new Octopus_Html_Page();
        }
        return self::$instance;
    }

    /**
     * Turns a file or URL into something appropriate to be outputted on a page.
     * @param String $file
     * @return String
     */
    public function urlify($file) {

    	if (preg_match('#^(https?:)?//#i', $file)) {
    		// Already an external url
    		return $file;
    	}

    	$app = Octopus_App::singleton();
    	if (!$app) return $file;

    	$root = empty($this->options['ROOT_DIR']) ? $app->ROOT_DIR : $this->options['ROOT_DIR'];
    	$urlBase = empty($this->options['URL_BASE']) ? $app->URL_BASE : $this->options['URL_BASE'];

    	$mtime = '';
    	if (is_file($file)) {
    	    $mtime = @filemtime($file);
    	    $mtime = $mtime ? "?$mtime" : '';
    	}

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
    	        return $urlBase . $remainder . $mtime;
    	    }
    	}

    	// Fall back to just URL_BASE/file
    	return u($file, null, array('URL_BASE' => $urlBase)) . $mtime;

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

    private static function compareAliases($x, $y) {

        $result = count($y['urls']) - count($x['urls']);
        if ($result !== 0) {
            return $result;
        }

        return $x['index'] - $y['index'];

    }


    private static function counter() {
        return self::$counter++;
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


    private function getPhysicalPathAllowMissing($file, Array $dirs) {
        $result = $this->getPhysicalPath($file, $dirs, true, false);
        if ($result !== false) return $result;
        return $this->dirs['ROOT_DIR'] . ltrim($file, '/');
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

}
