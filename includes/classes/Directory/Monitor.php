<?php

/**
 * Class that monitors one or more directories for changes to files.
 */
class Octopus_Directory_Monitor {

	private $dirs = array();
	private $removedDirs = array();
	private $lastMods = array();
	private $filters = array();

	/**
	 * Creates a new directory monitor.
	 * @param String $dir,... Directory(ies) to monitor.
	 */
	public function __construct($dir = null) {
		$args = func_get_args();
		foreach($args as $arg) {
			if ($arg) {
				$this->addDirectory($arg);
			}
		}
	}

	/**
	 * Adds a directory for monitoring.
	 * @param String $dir  Directory to monitor.
	 * @param boolean $deep Whether to descend into subdirectories.
	 * @return Octopus_Directory_Monitor $this
	 */
	public function addDirectory($dir, $deep = true) {
		$this->dirs[] = array('path' => $dir, 'deep' => $deep);
		return $this;
	}

	/**
	 * Removes $dir (and all subdirectories) from the monitor.
	 * @param String $dir Directory to remove
	 * @return Octopus_Directory_Monitor $thiss
	 */
	public function removeDirectory($dir) {
		$this->removedDirs[] = end_in('/', $dir);
		return $this;
	}

	/**
	 * Adds a filter used to determine whether to include a file in the
	 * array returned from ::getChangedFiles.
	 * @param String $filter A regex to be applied against the full filename.
	 * @return Octopus_Directory_Monitor $this
	 * @todo Support callback filters
	 */
	public function addFilter($filter) {
		$this->filters[] = $filter;
		return $this;
	}

	/**
	 * Removes all filters added using ::addFilter. Implicitly calls ::reset()
	 * @return Octopus_Directory_Monitor $this
	 */
	public function clearFilters() {
		$this->filters = array();
		$this->reset();
		return $this;
	}

	/**
	 * @return Array Filters added using ::addFilter
	 */
	public function getFilters() {
		return $this->filters;
	}

	/**
	 * Removes a filter added using ::addFilter. If a filter is actually
	 * removed, this implicitly calls ::reset.
	 * @param  String $filter
	 * @return Octopus_Directory_Monitor $this
	 */
	public function removeFilter($filter) {
		$newFilters = array();
		foreach($this->filters as $f) {
			if ($f != $filter) {
				$newFilters[] = $f;
			} else {
				$this->reset();
			}
		}
		$this->filters = $newFilters;
		return $this;
	}

	/**
	 * @return Array Files that have changed since the last time
	 * ::getChangedFiles was called.
	 */
	public function getChangedFiles() {

		clearstatcache();

		$changedFiles = array();
		$seenDirs = array();

		foreach($this->dirs as $dir) {
			$this->pollDirectory($dir['path'], $dir['deep'], $changedFiles, $seenDirs);
		}

		return $changedFiles;
	}

	/**
	 * Resets this monitor so that the next call to ::getChangedFiles() will not ignore
	 * any files it's seen before.
	 * @return Octopus_Directory_Monitor $this
	 */
	public function reset() {
		$this->lastMods = array();
		return $this;
	}

	private function pollDirectory($dir, $deep, Array &$changedFiles, Array &$seenDirs) {

		$dir = end_in('/', $dir);

		if (isset($seenDirs[$dir]) || $this->wasRemoved($dir)) {
			return;
		}

		$seenDirs[$dir] = true;

		$handle = opendir($dir);

		if (!$handle) {
			die();
			return;
		}

		while(($item = readdir($handle)) !== false) {

			if ($item === '.' || $item === '..') {
				continue;
			}

			$file = $dir . $item;

			if (is_file($file)) {

				$mod = @filemtime($file);

				if ($mod) {

					if (!isset($this->lastMods[$file]) || $this->lastMods[$file] < $mod) {

						if (!$this->filters || $this->passesFilters($file)) {
							$changedFiles[] = $file;
						}
						$this->lastMods[$file] = $mod;

					}

				}

			} else if ($deep && is_dir($file)) {
				$this->pollDirectory($file, $deep, $changedFiles, $seenDirs);
			}

		}

		closedir($handle);

	}

	private function passesFilters($file) {

		foreach($this->filters as $filter) {

			if (!preg_match($filter, $file)) {
				return false;
			}

		}

		return true;

	}

	private function wasRemoved($dir) {

		$len = strlen($dir);
		foreach($this->removedDirs as $removedDir) {
			if (strncasecmp($dir, $removeD, $len) === 0) {
				return true;
			}
		}

		return false;

	}

}