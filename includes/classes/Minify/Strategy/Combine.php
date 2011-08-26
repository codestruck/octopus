<?php

Octopus::loadClass('Octopus_Minify_Strategy');

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

		$cacheFile = $this->getCacheFile($uniqueHash, $deleteHash, $extension, $options);
		
		if (!$cacheFile) {

			$content = '';
			foreach($handledFiles as $f) {
				$content .= ($content ? "\n\n" : '') . file_get_contents($f);
			}

			$deleteHash = md5($deleteHash);

			$cacheFile = $this->saveCacheFile($uniqueHash, $deleteHash, $extension, $content);
		}

		return array(
			$cacheFile => $handledFiles
		);
	}

}

?>