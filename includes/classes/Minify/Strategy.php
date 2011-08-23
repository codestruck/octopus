<?php

/**
 * Class encapsulating a way of minifying content.
 */
abstract class Octopus_Minify_Strategy {

	/**
	 * @param $urls Mixed Either an array of URLs, or a single URL string.
	 * @param $options Array Environment options etc.
	 * @return An array where keys are the new urls, and the values are arrays of
	 * urls the key minifies.
	 */
	abstract public function getMinifiedUrls($urls, $options = array());

	protected function getCacheDir($options = array()) {
		
		$dir = get_option('OCTOPUS_CACHE_DIR', null, $options);
		
		if (!$dir) {
			throw new Octopus_Exception('OCTOPUS_CACHE_DIR not set.');
		}

		return rtrim($dir, '/') . '/';
	}

	protected function &getDirectoriesToSearch($options = array()) {

		$dirs = get_option(array('SITE_DIR', 'OCTOPUS_DIR', 'ROOT_DIR'), null, $options);

		foreach($dirs as $key => $dir) {
			if (!$dir) {
				unset($dirs[$key]);
			}
		}

		return $dirs;
	}

	protected function getCacheFile($hash, $extension, $options = array()) {
		
		$cacheDir = $this->getCacheDir($options);
		$file = $cacheDir . $hash . $extension;

		return is_file($file) ? $file : false;
	}

	protected function getFileForUrl($url, $dirs) {
		
		foreach($dirs as $dir) {
			$file = $dir . ltrim($url, '/');
			if (is_file($file)) {
				return $file;
			}
		}

		return false;
	}

	protected function getUrlForFile($file, $includeModTime = true, $options = array()) {
		
		if (is_array($includeModTime)) {
			$options = array_merge($includeModTime, $options);
			$includeModTime = true;
		}

		$rootDir = get_option('ROOT_DIR', null, $options);

		$urlFile = $file;
		if (starts_with($urlFile, $rootDir)) {
			$urlFile = substr($file, strlen($rootDir));
		}

		$urlBase = get_option('URL_BASE', null, $options);

		$url = rtrim($urlBase, '/') . '/' . ltrim($urlFile, '/');
		
		if ($includeModTime) {
			$url .= '?' . filemtime($file);
		} 

		return $url;
	}

	/**
	 * @return Whether $url looks like a local file.
	 */
	protected function looksLikeLocalFile($url) {
		return !preg_match('#^[a-z0-9_-]*://#i', $url);
	}

	/**
	 * Saves a cache file with the given content
	 * @param $uniqueHash String Hash uniquely identifying the content / mtime
	 * @param $deleteHash String Hash used to clear out old cached versions of this content.
	 * @param $content String Data to put in the cache file.
	 * @param $options Array helper options.
	 * @return String Physical path to the file.
	 */
	protected function saveCacheFile($uniqueHash, $deleteHash, $extension, $content, $options = array()) {
		
		$cacheDir = get_option('OCTOPUS_CACHE_DIR', null, $options) . 'combine/';

		@mkdir($cacheDir);

		if ($deleteHash) {

			// Remove old cache files for this content
			$oldFilesGlob = $cacheDir . $deleteHash . '-*' . $extension;
			$oldFiles = glob($oldFilesGlob);
			
			if ($oldFiles) {
				foreach($oldFiles as $f) {
					unlink($f);
				}
			}
		}

		$cacheFile = $cacheDir . $deleteHash . '-' . $uniqueHash . $extension;

		file_put_contents($cacheFile, $content);

		return $cacheFile;
	}
}


?>