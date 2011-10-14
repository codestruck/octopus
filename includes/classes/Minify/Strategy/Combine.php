<?php

/**
 * Minification strategy that combines multiple files into one.
 */
class Octopus_Minify_Strategy_Combine extends Octopus_Minify_Strategy {

	public function minify($files, $options = array()) {

		$result = array();
		$handledFiles = array();

		$deleteHash = '';
		$uniqueHash = '';

		$extension = '';

		clearstatcache();

		foreach($files as $file) {

			if (!$this->looksLikeLocalFile($file)) {
				continue;
			}

			if (!$extension) {
				$info = pathinfo($file);
				$extension = ($info['extension'] ? '.' : '') . $info['extension'];
			}

			$handledFiles[] = $file;
			$deleteHash .= '|' . $file;
			$uniqueHash .= '|' . $file . '?' . filemtime($file);
		}

		if (empty($handledFiles)) {
			return $handledFiles;
		}

		// see if there is a cache file available
		$uniqueHash = md5($uniqueHash);
		$deleteHash = md5($deleteHash);

		$cacheFile = $this->getCacheFile('combine', $uniqueHash, $deleteHash, $extension, $options);

		if (!$cacheFile) {

			$content = '';
			foreach($handledFiles as $f) {
				$content .= ($content ? "\n\n" : '') . file_get_contents($f);
			}

			$cacheFile = $this->saveCacheFile('combine', $uniqueHash, $deleteHash, $extension, $content);
		}

		if ($cacheFile) {
			return array(
				$cacheFile => $handledFiles
			);
		} else {
			return array();
		}
	}

}

?>