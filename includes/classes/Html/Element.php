<?php

/**
 * Helper for generating HTML.
 */
class SG_Html_Element {

    private $_tag;
    private $_attributes;
    private $_content;

    public function __construct($tag, $attrs = null, $content = null) {

        $this->_tag = $tag;
        $this->_attributes = ($attrs ? $attrs : array());
        $this->_content = $content;

    }

    public function __get($key) {
        return $this->getAttribute($key);
    }

    public function __set($key, $value) {
        $this->setAttribute($key, $value);
    }

    public function __unset($key) {
        $this->removeAttribute($key);
    }

    /**
     * Adds one or more CSS classes to this element.
     */
    public function addClass($class) {

        if (!is_array($class)) {

            $class = trim($class);
            if ($class === '') {
                return $this;
            }

            return $this->addClass(explode(' ', $class));
        }

        $existing = $this->getAttribute('class', '');

        foreach($class as $c) {
            $c = trim($c);
            if ($c && !preg_match('/\b' . preg_quote($c) . '\b/', $existing)) {
                $existing .= ($existing ? ' ' : '') . $c;
            }
        }

        return $this->setAttribute('class', $existing);
    }

    /**
     * Appends content to this element.
     */
    public function append($item) {

        if ($this->_content === null) {
            $this->_content = array();
        }

        $this->_content[] = $item;
    }

    /**
     * Sets elements of the style attribute.
     */
    public function css($key, $value = null) {

        if (is_array($key)) {
            $ar = $key;
        } else {
            $ar = array($key => $value);
        }

        $rawStyles = explode(';', $this->getAttribute('style', ''));
        $styles = array();

        foreach($rawStyles as $style) {
            if (preg_match('/^\s*(.+?)\s*:(.+)\s*(;|$)/', $style, $m)) {
                $styles[$m[1]] = $m[2];
            }
        }

        $styles = array_merge($styles, $ar);

        $styleAttr = '';
        foreach($styles as $key => $value) {
            $styleAttr .= $key . ': ' . $value . '; ';
        }

        $this->setAttribute('style', trim($styleAttr));

        return $this;
    }

    /**
     * Sets HTML5 data- attributes.
     */
    public function data($key, $value = null) {

        if (is_array($key)) {
            $data = $key;
        } else {
            $data = array($key => $value);
        }

        foreach($data as $key => $value) {
            $this->setAttribute('data-' . $key, $value);
        }

    }

    /**
     * Clears the content in this element.
     */
    public function &clear() {
        $this->_content = array();
        return $this;
    }

    public function getAttribute($key, $default = null) {
        return isset($this->_attributes[$key]) ? $this->_attributes[$key] : $default;
    }

    /**
     * Sets the inner HTML of this element.
     */
    public function html($html) {
        $this->_content = array($html);
    }

    public function setAttribute($key, $value) {
        if ($value === null) {
            unset($this->_attributes[$key]);
        } else {
            $this->_attributes[$key] = $value;
        }

        return $this;
    }

    public function removeAttribute($key) {
        unset($this->_attributes[$key]);
        return $this;
    }

    /**
     * Removes one or more CSS classes from this element.
     */
    public function removeClass($class) {

        if (!is_array($class)) {

            $class = trim($class);

            if ($class === '') {
                return $this;
            }

            return $this->removeClass(explode(' ', $class));
        }

        $existing = trim($this->getAttribute('class', ''));
        if ($existing === '') {
            return $this->removeAttribute('class');
        }

        foreach($class as $c) {
            $c = trim($c);
            if ($c) {
                $existing = preg_replace('/\s*' . preg_quote($c) . '\b/', '', $existing);
            }
        }

        $existing = trim($existing);
        if ($existing) {
            return $this->setAttribute('class', $existing);
        } else {
            return $this->removeAttribute('class');
        }
    }

    /**
     * Generates the HTML for this element, optionally outputting it.
     * @param $return bool True to return the generated HTML, false to output
     * it.
     */
    public function render($return = false) {

        $open = $this->renderOpenTag();
        $content = trim($this->renderContent());
        $close = $this->renderCloseTag($content);

        $result = $open . $content . $close;

        if ($return) {
            return $result;
        } else {
            echo $result;
            return $this;
        }

    }

    protected function renderOpenTag() {

        $result = '<' . $this->_tag;

        foreach($this->_attributes as $key => $value) {
            $result .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
        }

        return $result;
    }

    protected function renderCloseTag(&$renderedContent) {

        if ($renderedContent) {
            return '</' . $this->_tag . '>';
        } else {
            return ' />';
        }

    }

    protected function &renderContent() {

        $content = '';

        if (empty($this->_content)) {
            return $content;
        }

        $content = '>';
        $count = 0;

        foreach($this->_content as $c) {

            if (!$c) {
                continue;
            }
            if ($count > 0) $content .= "\n";

            $content .= $c;
        }

        return $content;
    }

    /**
     * Sets the inner text of this element.
     */
    public function text($text) {
        $this->_content = array(htmlspecialchars($text));
    }

    public function toggleClass($class) {

        if (!is_array($class)) {

            $class = trim($class);
            return $this->toggleClass(explode(' ', $class));

        }

        $existing = trim($this->getAttribute('class', ''));
        if ($existing) {

            foreach($class as $c) {

                $c = trim($c);
                if (!$c) {
                    continue;
                }

                $pattern = '/\s*' . preg_quote($c) . '\b/';
                $replaceCount = 0;
                $existing = preg_replace($pattern, '', $existing, -1, $replaceCount);

                if (!$replaceCount) {
                    // Classname wasn't found, so add it
                    $existing .= ($existing ? ' ' : '') . $c;
                }

            }

            return $this->setAttribute('class', trim($existing));


        } else {
            return $this->addClass($class);
        }
    }

    public function __toString() {
        return $this->render(true);
    }

}

?>
