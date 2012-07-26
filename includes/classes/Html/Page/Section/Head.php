<?php

/**
 * @internal
 * A section to be used for the <head> element. When rendered, it outputs the
 * entire <head> element (including opening and closing tag). This is so you
 * can do $PAGE.head in a template and get a <head> element.
 */
class Octopus_Html_Page_Section_Head extends Octopus_Html_Page_Section {

	public function render($return = false, $minify = true) {

		$result = array(
			'<head>',
			$this->page->renderTitle(true),
			$this->page->renderMeta(true),
			$this->renderCss(true, $minify),
			$this->page->renderJavascriptVars(true),
			$this->renderJavascript(true, $minify),
			'</head>'
		);
		$result = implode("\n", $result);

		if ($return) {
			return $result;
		} else {
			echo $result;
			return $this;
		}

	}

}