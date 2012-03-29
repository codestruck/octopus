<?php

	require_once(dirname(dirname(__FILE__)) . '/includes/functions/debug.php');

	$description = <<<END

octopus/tail

	Monitors one or more Octopus log files for updates and displays them
	formatted for an 80-column console.

	Usage:

		> octopus/tail [path/to/log/file]


END;

	date_default_timezone_set(@date_default_timezone_get());

	array_shift($argv); // Remove script name

	define('OCTOPUS_TAIL_DELAY_IDLE', 3);
	define('OCTOPUS_TAIL_DELAY_ACTIVE', 1);

	$monitor = new Octopus_Log_Monitor('octopus_display_log');
	$added = 0;

	if (count($argv) > 0) {

		while($arg = array_shift($argv)) {

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

	function octopus_display_log($file, $lastItemHash) {

		global $toDisplay;

		$contents = @file_get_contents($file);
		if (!$contents) return $lastItemHash;

		$contents = trim($contents);
		$contents = trim($contents, ',');
		$contents = @json_decode('[' . $contents . ']', true);

		if (!$contents) {
			return false;
		}

		$hash = md5($file);
		$lastFound = false;

		foreach($contents as &$item) {
			$hash = md5($hash . serialize($item));
			$item['hash'] = $hash;

			if ($item['hash'] === $lastItemHash) {
				// this was the last item shown
				$item['show'] = false;
				$lastFound = true;
			} else if ($lastFound) {
				$item['show'] = true;
			} else {
				$item['show'] = null;
			}
		}

		foreach($contents as &$item) {
			if ($item['show'] || (!$lastFound && $item['show'] === null)) {
				$toDisplay[] = $item;
			}
		}

		return $hash;
	}

	function octopus_display_log_item($item, $width = 80) {

		$text = Octopus_Log_Listener_Console::formatForDisplay(
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

		return $xTime - $yTime;

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