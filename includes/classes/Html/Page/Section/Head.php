<?php

/**
 * @internal
 * A section to be used for the <head> element. When rendered, it outputs the
 * entire <head> element (including opening and closing tag). This is so you
 * can do $PAGE.head in a template and get a <head> element.
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_Html_Page_Section_Head extends Octopus_Html_Page_Section {

	public function __get($name) {

		switch($name) {

			case 'content':
				return $this->getContent();

		}

		return parent::__get($name);

	}

	/**
	 * @return String The title, meta tags, css, javascript and other <link>
	 * tags for the 'head' section.
	 */
	public function getContent($minify = true) {

		$result = array(
			$this->page->renderTitle(true),
			$this->page->renderMeta(true),
			$this->renderCss(true, $minify),
			$this->renderJavascript(true, $minify),
			$this->page->renderLinks(true),
		);

		return implode("\n", $result);

	}

	public function render($return = false, $minify = true) {

		$result = '<head>' . $this->getContent($minify) . '</head>';

		if ($return) {
			return $result;
		} else {
			echo $result;
			return $this;
		}

	}

	public function renderJavascript($return = false, $minify = true) {

		// For <head> only, include javascript variables
		if ($return) {
			return implode(
				"\n",
				array(
					$this->page->renderJavascriptVars(true),
					parent::renderJavascript(true, $minify)
				)
			);
		}

		$this->page->renderJavascriptVars(false);
		parent::renderJavascript(false, $minify);
		return $this;

	}


}