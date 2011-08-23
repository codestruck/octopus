<?php

Octopus::loadClass('Octopus_Minify_Strategy');

/**
 * Minification strategy that combines multiple files into one.
 */
class Octopus_Minify_Strategy_Combine extends Octopus_Minify_Strategy {
	
	public function getMinifiedUrls($urls, $options = array()) {
		
		$result = array();
		$files = array();
		$handledUrls = array();
		$dirs = $this->getDirectoriesToSearch($options);
		
		$deleteHash = '';
		$uniqueHash = '';

		$extension = '';

		foreach($urls as $url) {

			if (!$this->looksLikeLocalFile($url)) {
				continue;
			}

			$file = $this->getFileForUrl($url, $dirs);

			if ($file) {

				if (!$extension) {
					$info = pathinfo($file);
					$extension = ($info['extension'] ? '.' : '') . $info['extension'];
				}

				$files[] = $file;
				$handledUrls[] = $url;
				$deleteHash .= '|' . $file;
				$uniqueHash .= '|' . $file . '?' . filemtime($file);
			}
		}

		if (empty($files)) {
			return $files;
		}

		// see if there is a cache file available
		$uniqueHash = md5($uniqueHash);

		$cacheFile = $this->getCacheFile($uniqueHash, $deleteHash, $extension, $options);
		
		if (!$cacheFile) {

			$content = '';
			foreach($files as $f) {
				$content .= ($content ? "\n\n" : '') . file_get_contents($f);
			}

			$deleteHash = md5($deleteHash);

			$cacheFile = $this->saveCacheFile($uniqueHash, $deleteHash, $extension, $content);
		}

		return array(
			$this->getUrlForFile($cacheFile, false, $options) => $handledUrls
		);
	}

}

?>