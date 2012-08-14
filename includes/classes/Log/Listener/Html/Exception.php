<?php

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_Log_Listener_Html_Exception {

	private $exception;

	public function __construct(Exception $ex) {
		$this->exception = $ex;
	}

	public function render() {

		$ex = $this->exception;
		$first = true;

		$result = array();

		do {

			if (!$first) {
				$result[] = '<hr class="octopus-debug-exception-sep" />';

				$result[] = '<h3 class="octopus-inner-exception">';
				$result[] = get_class($ex);

				$trace = Octopus_Debug::getNiceBacktrace($ex->getTrace());

				$lines = Octopus_Debug::getMostRelevantTraceLines(1, $trace);
				$line = array_shift($lines);

				if ($line) {
					$result[] = "at {$line['nice_file']}, line {$line['line']}";
				}

				$result[] = '</h3>';
			}
			$first = false;

			$result[] = htmlspecialchars($ex->getMessage(), ENT_QUOTES, 'UTF-8');

			// NOTE: 5.2 doesn't support getPrevious
			if (method_exists($ex, 'getPrevious')) {
				$ex = $ex->getPrevious();
			} else {
				$ex = null;
			}

		} while($ex);

		return implode(' ', $result);
	}

	public function __toString() {
		return $this->render();
	}

}