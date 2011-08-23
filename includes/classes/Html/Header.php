<?php

Octopus::loadClass('Octopus_Html_Page');

/**
 * @deprecated
 */
class Octopus_Html_Header extends Octopus_Base {

    private $page;

    public function __construct($page = null) {
        $this->page = $page ? $page : Octopus_Html_Page::singleton();
    }

    public static function &singleton() {
        return Octopus_Base::base_singleton('Octopus_Html_Header');
    }

    /**
     * @deprecated
     */
    function getHeader() {
        return  $this->page->renderMeta(true) .
                $this->page->renderCss(true) .
                $this->page->renderLinks(true) .
                $this->page->renderJavascript(true);
    }

    function addJavascript($file, $attributes = array()) {
        $this->page->addJavascript($file, $attributes);
    }

    function getJavascriptHeader() {
        return $this->page->renderJavascript(true);
    }

    function addCss($file, $attributes = array()) {
        $this->page->addCss($file, $attributes);
    }

    function getCssHeader() {
        return $this->page->renderCss(true);
    }

    
    function useTinyMce() {
        $this->addJavascript('scripts/tiny_mce/tiny_mce.js');
        $this->addJavascript('scripts/tinyMceInit.js');
    }

    function useManageTable() {
        $this->addJavascript('scripts/ajaxToggles.js');
    }

    function useTabs() {
        $this->addJavascript('../includes/js/jquery/jquery.blockUI.js');
        $this->addJavascript('../includes/js/sg_tabs.js');
        $this->addCss('../includes/css/tabs.css');
        $this->addJavascript(JS_JQUERY_UI);
        $this->addCss(CSS_JQUERY_UI);
    }

}

?>
