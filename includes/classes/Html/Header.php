<?php

class SG_Html_Header extends SG_Base {

    function SG_Html_Header() {
        $this->js = array();
        $this->css = array();
        $this->trackModified = true;
        $this->baseDir = '';
        $this->URL_BASE = URL_BASE;
    }

    function &singleton() {
        return SG_Base::base_singleton('SG_Html_Header');
    }

    /**
     *
     * @return string CSS and JS HTML headers
     */
    function getHeader() {

        $output = "\n";
        $output .= $this->getCssHeader();
        $output .= $this->getJavascriptHeader();
        return $output;

    }

    /**
     * Add Javascript include
     *
     * @param string $file name of javascript file
     * @return void
     */
    function addJavascript($file, $attributes = array()) {
        $this->js[$file] = $attributes;
    }

    function getJavascriptHeader() {

        $headers = '';

        foreach ($this->js as $file => $attributes) {

            $headers .= '<script type="text/javascript" src="' . $this->getFileModifiedLink($file) . '"' . $this->buildAttributeString($attributes) . '></script>';
            $headers .= "\n";

        }

        return $headers;

    }

    /**
     * Add CSS include
     *
     * @param string $file name of css file
     * @return void
     */
    function addCss($file, $attributes = array()) {
        $this->css[$file] = $attributes;
    }

    function getCssHeader() {

        $headers = '';

        foreach ($this->css as $file => $attributes) {

            $headers .= '<link rel="stylesheet" type="text/css" href="' . $this->getFileModifiedLink($file) . '"' . $this->buildAttributeString($attributes) . ' />';
            $headers .= "\n";

        }

        return $headers;

    }

    function getFileModified($file) {

        if ($this->URL_BASE != '/') {
            $file = preg_replace('|^' . $this->URL_BASE . '|', '/', $file);
        }

        $pos = strpos($file, '?');
        if ($pos) {
            $file = substr($file, 0, $pos);
        }

        $fullfile = ROOT_DIR . $file;

        if ($this->baseDir) {
            $basefullfile = ROOT_DIR . $this->baseDir . '/' . $file;
            if (is_file($basefullfile)) {
                $fullfile = $basefullfile;
            }
        }

        $mtime = filemtime($fullfile);

        return $mtime;
    }

    function getFileSrc($file) {

        $rawFile = $file;

        // get relative base file path ?
        if ($this->URL_BASE != '/') {
            $rawFile = preg_replace('|^' . $this->URL_BASE . '|', '/', $file);
        }

        $lastDot = strrpos($rawFile, '.');
        $src = substr($rawFile, 0, $lastDot) . '_src' . substr($rawFile, $lastDot);

        $fullSrc = ROOT_DIR . $src;

        if ($this->baseDir && $rawFile[0] != '/') {
            $fullSrc = ROOT_DIR . $this->baseDir . '/' . $src;
        }

        if (is_file($fullSrc)) {
            $lastDot = strrpos($file, '.');
            $src = substr($file, 0, $lastDot) . '_src' . substr($file, $lastDot);

            return $src;
        }

        return $file;

    }

    function getFileModifiedLink($file) {

        // allow remote urls to be used
        if (substr($file, 0, 4) == 'http') {
            return $file;
        }

        if ($this->trackModified) {

            $srcFile = $this->getFileSrc($file);
            $srcMtime = $this->getFileModified($srcFile);
            $mtime = $this->getFileModified($file);

            if ($srcMtime > $mtime) {
                $file = $srcFile;
                $mtime = $srcMtime;
            }

            $end = '';

            $pos = strpos($file, '?');
            if ($pos) {
                $file = $file . '&amp;' . $mtime;
            } else {
                $file = $file . '?' . $mtime;
            }

        }

        return u($file);
    }

    function buildAttributeString($attrs) {

        $attributes = '';

        foreach ($attrs as $attr => $value) {
            $attributes .= " $attr=\"$value\"";
        }

        return $attributes;

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
