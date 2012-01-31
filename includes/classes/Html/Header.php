<?php

/**
 * Shim wrapped around Octopus_Html_Page. Method calls are forwarded on to the
 * wrapped Html_Page instance.
 * @deprecated Use Html_Page instead.
 */
class Octopus_Html_Header {

    private $page;
    private static $instance = null;

    public function __construct(Octopus_Html_Page $page) {
        $this->page = $page;
        $page->addJavascriptMinifier('src');
        $page->addCssMinifier('src');
    }

    public function __call($method, $args) {
        // Forward method calls on to the wrapped Html_Page instance.
        return call_user_func_array(array($this->page, $method), $args);
    }

    public static function singleton() {

        if (!self::$instance) {
            self::$instance = new Octopus_Html_Header(Octopus_Html_Page::singleton());
        }

        return self::$instance;
    }

    /**
     * @deprecated
     * @return string CSS and JS HTML headers
     */
    public function getHeader() {

        $output = "\n";
        $output .= $this->renderCss(true);
        $output .= $this->renderJavascript(true);
        return $output;

    }

    /**
     * @deprecated
     */
    public function getJavascriptHeader() {
        return $this->renderJavascript(true);
    }

    /**
     * @deprecated
     */
    public function getCssHeader() {
        return $this->renderCss(true);
    }

}

?>