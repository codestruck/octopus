<?php

/**
 * Helper that renders a single variable as HTML.
 */
class Octopus_Log_Listener_Html_Variable {

	private $value;

	public function __construct($value) {
		$this->value = $value;
	}

	/**
	 * @return String The HTML for the 'raw' view of the variable, if any.
	 */
	public function getRawContent() {

	}

	public function __toString() {
		return Octopus_Debug::dumpToString($this->value, 'html', true);
	}

}