<?php

/**
 * Helper used to render variables dumped via dump_r or Octopus_Debug::dump
 */
class Octopus_Debug_DumpedVars implements Dumpable, ArrayAccess {

	private $vars;

	public function __construct(Array $vars) {
		$this->vars = $vars;
	}

	public function __dumpHtml() {

		$result = array();

		foreach($this->vars as $key => $var) {
			$result[] = Octopus_Debug::dumpToString($var, 'html');
			if (count($result) > 1) $result[] = '<hr />';
		}

		return implode("\n", $result);
	}

	public function __dumpText() {

		$result = array();
		foreach($this->vars as $key => $var) {
			if ($result) $result[] = str_repeat(Octopus_Log_Listener_Console::CHAR_LIGHT_LINE, 80);
			$result[] = Octopus_Debug::dumpToString($var, 'text', true);
		}

		return implode("\n", $result);
	}

	public function __toString() {
		return $this->__dumpText();
	}

	public function offsetExists($offset) {
		return array_key_exists($offset, $this->vars);
	}

	public function offsetGet($offset) {
		return $this->vars[$offset];
	}

	public function offsetSet($offset, $value) {
		throw new Octopus_Exception("Setting values on Octopus_Debug_Dumped_Vars is not supported.");
	}

	public function offsetUnset($offset) {
		throw new Octopus_Exception("Unsetting values on Octopus_Debug_Dumped_Vars is not supported.");
	}

}
