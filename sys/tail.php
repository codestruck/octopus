<?php

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
		$monitor->addDir(dirname(dirname(dirname(__FILE__))) . '/_private/log');

	}

	$lastItem = 0;
	$nextDelay = OCTOPUS_TAIL_DELAY_ACTIVE;

	while(true) {

		if ($monitor->poll()) {
			$nextDelay = OCTOPUS_TAIL_DELAY_ACTIVE;
		} else {
			$nextDelay = OCTOPUS_TAIL_DELAY_IDLE;
		}

		sleep($nextDelay);
	}

	function octopus_display_log($file, $lastItemHash) {

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
				octopus_display_log_item($item);
			}
		}

		return $hash;
	}

	function octopus_display_log_item($item) {

		echo <<<END

{$item['time']}

END;

		var_dump($item['message']);

		echo "\n";

	}

	function octopus_get_log_file($file) {

		return $file;

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

			foreach($this->findLogFilesInDir($dir) as $logFile) {

				$mtime = @filemtime($logFile);
				if (!$mtime) continue; // file not found

				if (!isset($this->lastMods[$logFile])) {
					echo "\nMonitoring $logFile...";
				}

				$this->lastMods[$logFile] = filemtime($logFile);
				$lastItem = isset($this->lastItems[$logFile]) ? $this->lastItems[$logFile] : false;

				$func = $this->callback;
				$this->lastItems[$logFile] = $func($logFile, $lastItem);

				$hadChanges = true;
			}


		}

		return $hadChanges;
	}

	private function findLogFilesInDir($dir) {

		$found = array();

		$dir = rtrim($dir, '/') . '/';
		foreach(glob($dir . '*.log') as $file) {

			$name = basename($file, '.log');
			if (preg_match('/\.\d\d\d$/', $name)) {
				continue;
			}

			$found[] = $file;
		}

		return $found;
	}

}