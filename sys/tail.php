<?php

	require_once(dirname(dirname(__FILE__)) . '/includes/functions/debug.php');

	$description = <<<END

octopus/tail

	Monitors one or more Octopus log files for updates and displays them
	formatted for an 80-column console.

	Usage:

		> octopus/tail [path/to/log/dir] [options]

	Options:

		--full-stack
			Display full stack traces, rather than just the line that
			triggered the log event.


END;

	date_default_timezone_set(@date_default_timezone_get());

	array_shift($argv); // Remove script name

	define('OCTOPUS_TAIL_DELAY_IDLE', 1);
	define('OCTOPUS_TAIL_DELAY_ACTIVE', 1);

	$monitor = new Octopus_Log_Monitor('octopus_display_log');
	$added = 0;
	$fullStackTraces = false;

	if (count($argv) > 0) {

		while($arg = array_shift($argv)) {

			if (strcmp($arg, '--full-stack') === 0) {
				$fullStackTraces = true;
				continue;
			}

			if (is_dir($arg)) {
				$monitor->addDir($arg);
				$added++;
			}

		}

	}

	if (!$added) {

		// nothing == monitor LOG_DIR
		$monitor->addDir(dirname(dirname(dirname(__FILE__))) . '/_private');

	}

	$lastItem = 0;
	$nextDelay = OCTOPUS_TAIL_DELAY_ACTIVE;
	$toDisplay = array();

	while(true) {

		$toDisplay = array();

		if ($monitor->poll()) {
			$nextDelay = OCTOPUS_TAIL_DELAY_ACTIVE;
		} else {
			$nextDelay = OCTOPUS_TAIL_DELAY_IDLE;
		}

		usort($toDisplay, 'octopus_compare_log_items');

		foreach($toDisplay as $item) {
			octopus_display_log_item($item);
		}

		sleep($nextDelay);
	}

	function octopus_display_log($file, $lastItemID) {

		global $toDisplay;

		$contents = @file_get_contents($file);
		if (!$contents) return $lastItemID;

		$contents = trim($contents);
		$contents = trim($contents, ',');
		$contents = @json_decode('[' . $contents . ']', true);

		if (!$contents) {
			return false;
		}

		$newLastItemID = $lastItemID;
		$lastFound = !$lastItemID;

		$show = array();

		foreach($contents as &$item) {

			if (!isset($item['id'])) {
				$item['id'] = md5($file . serialize($item));
			}

			if ($item['id'] === $lastItemID) {
				// this was the last item shown
				$lastFound = true;
			} else if ($lastFound) {
				$show[] = $item;
				$newLastItemID = $item['id'];
			}
		}

		if (!$lastItemID && count($show) > 1) {
			$show = array(array_pop($show));
		}

		foreach($show as $itemToShow) {
			$toDisplay[] = $itemToShow;
		}

		return $newLastItemID;
	}

	function octopus_display_log_item($item, $width = 80) {

		global $fullStackTraces;

		$console = new Octopus_Log_Listener_Console(false);
		$console->stackTraceLines = $fullStackTraces ? -1 : 1;
		$console->renderInColor = true;

		$text = $console->formatForDisplay(
			$item['message'],
			$item['log'],
			$item['level'],
			strtotime($item['time']),
			$item['trace'],
			true,
			$width
		);

		$fp = fopen('php://stderr', 'w');
		if ($fp) {
			fputs($fp, $text);
			fclose($fp);
		}

	}

	function octopus_get_log_file($file) {

		return $file;

	}

	function octopus_compare_log_items($x, $y) {

		$xTime = $x['time'];
		$yTime = $y['time'];

		if (!is_numeric($xTime)) $xTime = strtotime($xTime);
		if (!is_numeric($yTime)) $yTime = strtotime($yTime);

		$result = $xTime - $yTime;

		if (!$result) {
			return $result;
		}

		$xIndex = isset($x['index']) ? $x['index'] : 0;
		$yIndex = isset($y['index']) ? $y['index'] : 0;

		$result = $xIndex - $yIndex;

		if (!$result) {
			return $result;
		}

		return strcmp($x['id'], $y['id']);

	}

class Octopus_Log_Monitor {

	private $dirs = array();
	private $callback;
	private $lastMods = array();
	private $lastItems = array();

	public function __construct($callback) {
		$this->callback = $callback;
	}

	public function addDir($dir) {
		$this->dirs[] = $dir;
	}


	/**
	 * @return Boolean True if anything was found, false otherwise.
	 */
	public function poll() {

		$hadChanges = false;

		foreach($this->dirs as $dir) {

			if (!is_dir($dir)) {
				continue;
			}

			$hadChanges = $this->pollDirectory($dir) || $hadChanges;

		}

		return $hadChanges;
	}

	private function pollDirectory($dir) {

		$hadChanges = false;

		$dir = rtrim($dir, '/') . '/';

		$h = opendir($dir);
		if (!$h) {
			echo "Could not open directory $dir\n\n";
			continue;
		}

		while(($item = readdir($h)) !== false) {

			if ($item == '.' || $item == '..') {
				continue;
			}

			$file = $dir . $item;

			if (is_dir($file)) {
				$this->pollDirectory($file);
				continue;
			}

			if (!preg_match('/\.log$/', $item) || preg_match('/\.\d+\.log$/', $item)) {
				continue;
			}

			$mtime = @filemtime($file);
			if (!$mtime) continue; // file not found

			if (!isset($this->lastMods[$file])) {
				echo "Monitoring $file...\n\n";
			}

			$this->lastMods[$file] = filemtime($file);
			$lastItem = isset($this->lastItems[$file]) ? $this->lastItems[$file] : false;

			$func = $this->callback;
			$this->lastItems[$file] = $func($file, $lastItem);

			$hadChanges = true;
		}

		closedir($h);

		return $hadChanges;
	}


}