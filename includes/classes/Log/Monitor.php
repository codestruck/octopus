<?php

/**
 * Class that monitors one or more log files / directories and returns new items
 * as they appear in them.
 * Uses log files as generated by Octopus_Log_Listener_File.
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_Log_Monitor {

	private $directoryMonitor = null;
	private $fileMonitors = array();
	private $lastItem = null;

	/**
	 * Adds a directory to be monitored for changes.
	 * @param String  $dir  Directory to monitor
	 * @param boolean $deep Whether to also monitor subdirectories.
	 * @return Octopus_Log_Monitor $this
	 */
	public function addDirectory($dir, $deep = true) {

		if (!$this->directoryMonitor) {
			$this->directoryMonitor = new Octopus_Directory_Monitor();
			$this->directoryMonitor->addFilter('/\.log$/');
		}

		$this->directoryMonitor->addDirectory($dir, $deep);

		return $this;
	}

	/**
	 * Adds a single file to be watched for changes.
	 * @param String $file File to watch.
	 * @return Octopus_Log_Monitor $this
	 * @throws Octopus_Exception If $file is not a valid filename.
	 */
	public function addFile($file) {

		$path = pathinfo($file);
		if (!$path) throw new Octopus_Exception("Invalid file: $file");

		$monitor = new Octopus_Directory_Monitor();
		$monitor->addDirectory($path['dirname']);

		$filter = '#/' . preg_quote($path['filename'], '#');

		// Support log rotation markers before the extension
		$filter .= '(\.\d+)?';

		if (!empty($path['extension'])) {
			$filter .= '\.' . $path['extension'];
		}
		$filter .= '$#';

		$monitor->addFilter($filter);
		$this->fileMonitors[] = $monitor;

		return $this;

	}

	/**
	 * @return Array New log items since the last time ::poll was called.
	 */
	public function poll() {

		$cleanupRx = '/\.\d+($|\.)/';

		$changedFiles = array();

		if ($this->directoryMonitor) {

			foreach($this->directoryMonitor->getChangedFiles() as $file) {

				$file = preg_replace($cleanupRx, '$1', $file);
				$changedFiles[$file] = true;

			}

		}

		foreach($this->fileMonitors as $fm) {
			foreach($fm->getChangedFiles() as $file) {
				$file = preg_replace($cleanupRx, '$1', $file);
				$changedFiles[$file] = true;
			}
		}

		$items = array();
		foreach($changedFiles as $file => $unused) {
			$this->scanFileForNewItems($file, $items);
		}

		// filter items by unique hash
		$filteredItems = array();
		foreach($items as $item) {
			$id = isset($item['id']) ? $item['id'] : md5(serialize($item));
			$filteredItems[$id] = $item;
		}

		$items = array_values($filteredItems);

		usort($items, array('Octopus_Log', 'compareLogItems'));

		$count = count($items);

		if ($count) {
			$this->lastItem = $items[$count - 1];
		}

		return $items;
	}

	private function scanFileForNewItems($basefile, &$items) {

		$path = pathinfo($basefile);

		// Support '.xxx.log' rotation files
		for($i = 0; $i < 100; $i++) {

			$file = $path['dirname'] . '/' . $path['filename'];

			if ($i > 0) $file .= sprintf('.%03d', $i);
			if (!empty($path['extension'])) $file .= '.' . $path['extension'];

			if (!is_file($file)) {
				return;
			}

			$fileItems = Octopus_Log_Listener_File::readFile($file);
			if (!$fileItems) continue;

			foreach($fileItems as $item) {

				if (!$this->lastItem || Octopus_Log::compareLogItems($item, $this->lastItem) > 0) {
					$items[] = $item;
				}

			}

		}
	}


}