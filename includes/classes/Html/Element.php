<?php

/**
 * Helper for generating HTML.
 */
class Octopus_Html_Element {

    protected $_tag;
    private $_attributes;
    private $_content;

    // Helper for displaying attributes in a standard order.
    protected static $attributeWeights = array(
        'type' => -15,
        'href' => -13,
        'id' => -12,
        'method' => -10,
        'action' => -9,
        'class' => -8,
        'style' => -7,
        'name' => -6,
        'value' => -5,
        'alt' => 1,
        'selected' => 8,
        'checked' => 8,
        'autofocus' => 9,
        'required' => 10,
    );

    // Helper for determining which attributes should not be rendered with a
    // value when true.
    protected static $noValueAttrs = array(
        'autofocus' => true,
        'checked' => true,
        'multiple' => true,
        'required' => true,
        'selected' => true
    );

    protected $requireCloseTag = false;

    public function __construct($tag, $attrs = null, $content = null) {

        if (is_string($attrs) && $content === null) {
            $content = $attrs;
            $attrs = null;
        }

        $this->_tag = $tag;
        $this->_attributes = ($attrs ? $attrs : array());

        if (is_array($content)) {
            $this->_content = $content;
        } else if ($content) {
            $this->_content = array($content);
        } else {
            $this->_content = array();
        }

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
     * Get or set attributes, like jQuery's .attr() method.
     */
    public function attr(/* variable */) {

        $args = func_get_args();

        switch(count($args)) {

            case 1:

                $arg = array_shift($args);

                if (is_array($arg)) {
                    foreach($arg as $attr => $value) {
                        $this->setAttribute($attr, $value);
                    }
                    return $this;
                } else {
                    return $this->getAttribute($arg);
                }

            case 2:

                $attr = array_shift($args);
                $value = array_shift($args);
                $this->setAttribute($attr, $value);

                return $this;

            default:

                throw new Octopus_Exception('attr can only be called with 1 or 2 arguments, not ' . count($args));
        }

    }

    /**
     * Adds one or more CSS classes to this element.
     */
    public function addClass() {

        $args = func_get_args();
        if (empty($args)) return $this;

        if (count($args) == 1) {
            $class = array_shift($args);
        } else {
            foreach($args as $arg) {
                $this->addClass($arg);
            }
            return $this;
        }

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

        if ($item) {
            $this->_content[] = $item;
        }

        return $this;
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

    public function children() {
        return $this->_content;
    }

    /**
     * @return Array All attributes on this element.
     */
    public function getAttributes() {

        // Put attributes in a standard order (for aesthetics as well as
        // to help the gzipper reduce page size).
        uksort($this->_attributes, array($this, '_compareAttributes'));

        return $this->_attributes;
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

        $alreadySet = isset($this->_attributes[$key]);

        if ($value === null) {

            if (!$alreadySet) {
                // No change
                return $this;
            }

            $oldValue = $this->_attributes[$key];
            unset($this->_attributes[$key]);

            $this->attributeChanged($key, $oldValue, $value);

        } else {

            if ($alreadySet && $this->_attributes[$key] === $value) {
                // No change
                return $this;
            }

            $oldValue = $alreadySet ? $this->_attributes[$key] : null;
            $this->_attributes[$key] = $value;
            $this->attributeChanged($key, $oldValue, $value);

        }

        return $this;
    }

    /**
     * Sets multiple attributes.
     */
    public function &setAttributes($attributes) {

        if (!empty($attributes)) {

            foreach($attributes as $attr => $value) {
                $this->setAttribute($attr, $value);
            }
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

    /**
     * Clears all attributes and removes all content.
     */
    public function reset() {
        $this->_attributes = array();
        $this->_content = array();
    }

    protected function renderOpenTag() {

        $result = '<' . $this->_tag;

        foreach($this->getAttributes() as $key => $value) {

            $rendered = self::renderAttribute($key, $value);
            if ($rendered) {
                $result .= ' ' . $rendered;
            }
        }

        return $result;
    }

    /**
     * @return string HTML representation of the given attribute/value combo.
     */
    protected static function renderAttribute($attr, $value, $alreadyEscaped = false) {

        // Support e.g., 'autofocus' and 'required', which are rendered
        // without values.
        $hasValue = empty(self::$noValueAttrs[$attr]);

        if (!($hasValue || $value)) {
            return '';
        }

        if (!$alreadyEscaped) {
            $attr = htmlspecialchars($attr);
            $value = htmlspecialchars($value);
        }

        if ($hasValue) {
            return $attr . '="' . $value . '"';
        } else if ($value) {
            return $attr;
        } else {
            return '';
        }
    }

    protected function renderCloseTag(&$renderedContent) {

        if ($renderedContent || $this->requireCloseTag) {
            return '</' . $this->_tag . '>';
        } else {
            return ' />';
        }

    }

    protected function &renderContent() {

        $content = '';

        if (empty($this->_content)) {
            if ($this->requireCloseTag) {
                $content = '>';
            }

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
    public function text(/* $text */) {

        switch(func_num_args()) {

            case 0:
                return $this->getText();

            default:
                $args = func_get_args();
                $this->_content = array_map('htmlspecialchars', $args);
                return $this;
        }
    }

    private function getText() {

        $text = '';

        foreach($this->children() as $child) {

            $childText = '';

            if (is_string($child)) {
                $childText = $child;
            } else if ($child instanceof Octopus_Html_Element) {
                $childText = $child->text();
            }

            if ($childText) {
                $text .= ($text ? ' ' : '') . $childText;
            }

        }

        return htmlspecialchars_decode($text);

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

    /**
     * Hook that is called whenever an attribute changes.
     */
    protected function attributeChanged($attr, $oldValue, $newValue) {
    }

    private static function _compareAttributes($x, $y) {

        $xWeight = (isset(self::$attributeWeights[$x]) ? self::$attributeWeights[$x] : 0);
        $yWeight = (isset(self::$attributeWeights[$y]) ? self::$attributeWeights[$y] : 0);

        $result = $xWeight - $yWeight;
        if ($result != 0) return $result;


        // Alphabetize unweighted attributes for maybe some
        // compression benefits?
        return strcasecmp($x, $y);
    }

}

?>
