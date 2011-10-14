<?php

/**
 *
 */
class Octopus_Html_Header extends Octopus_Html_Page {

	private static $instance = null;

	public function __construct($options = array()) {
		parent::__construct($options);
		$this->addJavascriptMinifier('src');
		$this->addCssMinifier('src');
	}

    public static function singleton() {

    	if (!self::$instance) {
    		self::$instance = new Octopus_Html_Header();
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


    function useTinyMce() {
        $this->addJavascript('/scripts/tiny_mce/tiny_mce.js');
        $this->addJavascript('/scripts/tinyMceInit.js');
    }

    function useManageTable() {
        $this->addJavascript('/admin/scripts/ajaxToggles.js');
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