<?php

/**
 * A logical section of a page. An Octopus_Html_Page instance might be composed
 * of multiple sections, each of which can contain its own set of javascript
 * and css includes.
 *
 * @prop String $scripts The rendered HTML for all <script> tags in this section.
 * @prop String $css The render HTML for all CSS <link> tags in this section.
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_Html_Page_Section implements ArrayAccess {

    protected $name;
    protected $page;

    private $_scripts = array();
    private $removedScripts = array();

    private $_css = array();
    private $removedCss = array();

    private static $counter = 0;

    /**
     * @param String $name
     * @param Array $options
     */
    public function __construct($name, Octopus_Html_Page $page) {
        $this->name = $name;
        $this->page = $page;
    }

    public function __get($name) {

        switch($name) {

            case 'scripts':
                return $this->renderJavascript(true);

            case 'css':
                return $this->renderCss(true);

        }

    }

    public function __toString() {
        return $this->render(true);
    }

    /**
     * @see Octopus_Html_Page::addCss()
     */
    public function addCss($file, $weight = null, $attributes = array()) {

        if (is_string($attributes)) {
            // addCss($file, $weight, 'media')
            $attributes = array('media' => $attributes);
        }

        if (is_array($weight)) {
            // addCss($file, $attributes)
            $attributes = $weight;
            $weight = null;
        }

        if ($weight !== null && !is_numeric($weight)) {
            // addCss($file, 'media')
            $attributes['media'] = $weight;
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


        $index = self::counter();

        $info = compact('file', 'attributes', 'index');
        if ($ie) $info['ie'] = $ie;
        if ($weight !== null) $info['weight'] = $weight;

        $this->_css[$index] = $info;

        return $this;
    }

    /**
     * @see Octopus_Html_Page::addJavascript
     * @param string $file
     * @param Number $weight
     * @param Array $attributes
     */
    public function addJavascript($file, $weight = null, $attributes = array()) {

        // Support addJavascript($file, $attributes)
        if (is_array($weight) && $attributes === null) {
            $attributes = $weight;
            $weight = null;
        }

        if (isset($attributes['weight'])) {
            $weight = $attributes['weight'];
            unset($attributes['weight']);
        }

        // index is used to help sort items with the same weight - items added
        // first get sorted before those added later
        $index = self::counter();

        $script = compact('file', 'attributes', 'index');
        if ($weight !== null) $script['weight'] = $weight;

        $this->_scripts[] = $script;

        return $this;
    }

    /**
     * @param  boolean $minify
     * @return Array
     */
    public function getCssFiles($minify = true) {

        $css = $this->_css;

        usort($css, 'Octopus_Html_Page::compareWeights');

        foreach($css as $index => &$item) {

            $item['file'] = $this->page->findFile($item['file']);

            if ($this->wasFileRemoved($item['file'], $this->removedCss)) {
                unset($css[$index]);
                unset($this->_css[$index]);
                unset($item);
                continue;
            }

        }

        $css = $this->page->minify('css', $css);

        foreach($css as &$item) {
            $item['file'] = $this->page->urlify($item['file']);
            if (!isset($item['weight'])) $item['weight'] = 0;
        }

        return $css;
    }

    /**
     * @param boolean $minify
     * @return Array
     */
    public function getJavascriptFiles($minify = true) {

        $scripts = $this->_scripts;

        foreach($scripts as $index => &$item) {

            $item['file'] = $this->page->findFile($item['file']);

            if ($this->wasFileRemoved($item['file'], $this->removedScripts)) {
                unset($scripts[$index]);
                unset($this->_scripts[$index]);
            }

        }
        unset($item);

        usort($scripts, 'Octopus_Html_Page::compareWeights');
        $scripts = self::mergeDuplicates($scripts);

        if ($minify) {
            $scripts = $this->page->minify('javascript', $scripts);
        }

        foreach($scripts as &$item) {
            $item['file'] = $this->page->urlify($item['file']);
        }
        unset($item);

        return $scripts;

    }

    /**
     * @return String
     */
    public function getName() {
        return $this->name;
    }

    /**
     * @internal
     */
    public function offsetExists($name) {
        return true;
    }

    /**
     * @param String $name
     * @return String
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


    /**
     * Removes a CSS file.
     */
    public function removeCss($file) {
        $this->removedCss[$file] = true;
        return $this;
    }

    /**
     * @param String $file
     * @return Octopus_Html_Page_Section
     */
    public function removeJavascript($file) {
        $this->removedScripts[$file] = true;
        return $this;
    }

    public function render($return = false, $minify = true) {

        $result = implode(
            "\n",
            array(
                $this->renderCss(true, $minify),
                $this->renderJavascript(true, $minify)
            )
        );

        if ($return) {
            return $result;
        } else {
            echo $result;
            return $this;
        }

    }

    /**
     * Renders the section containing all CSS links.
     * @param boolean $return
     * @param boolean $minify
     * @return String|Octopus_Html_Page
     */
    public function renderCss($return = false, $minify = true) {

        if (empty($this->_css)) {
            return $return ? '' : $this;
        }

        $html = '';
        $css = $this->getCssFiles($minify);

        foreach($css as $info) {

            $el = new Octopus_Html_Element('link');
            $el->href = $info['file'];
            $el->type = "text/css";
            $el->rel = "stylesheet";
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

    /**
     *
     * @param boolean $return Whether to return or output the <script> tag(s).
     * @param boolean $minify
     * @param boolean $includeVars
     * @return String|Octopus_Html_Section
     */
    public function renderJavascript($return = false, $minify = true) {

        $scripts = $this->getJavascriptFiles($minify);
        $html = '';

        foreach($scripts as $info) {

            $el = new Octopus_Html_Element('script');
            $el->type = 'text/javascript';
            $el->src = $info['file'];

            $html .= $el->render(true);
        }

        if ($return) {
            return $html;
        }

        echo $html;
        return $this;
    }

    private static function counter() {
        return self::$counter++;
    }

    private static function getCloseConditionalComment($info) {
        if (!isset($info['ie'])) {
            return '';
        }

        return <<<END
<![endif]-->
END;
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

    private static function mergeDuplicates($items) {

        $byFile = array();
        foreach($items as $item) {

            $f = $item['file'];

            if (isset($byFile[$f])) {
                $byFile[$f] = array_merge($byFile[$f], $item);
            } else {
                $byFile[$f] = $item;
            }

        }

        return array_values($byFile);

    }

    private function wasFileRemoved($file, &$removedFiles) {

        foreach($removedFiles as $removedFile => $unused) {

            $normalizedRemovedFile = $this->page->findFile($removedFile);

            if ($file == $normalizedRemovedFile) {
                unset($removedFiles[$removedFile]);
                return true;
            }

        }

        return false;

    }

}