<?php

class Octopus_Html_Page_DefaultUrlifier {

	private $app;

	public function __construct($app = null) {
		$this->app = $app;
	}

	public function getUrlForFile($file, Octopus_Html_Page $page) {

		$app = $this->app ? $this->app : Octopus_App::singleton();
		if (!$app) return $file;

		$root = $app->ROOT_DIR;

		$mtime = '';
		if (is_file($file)) {
		    $mtime = @filemtime($file);
		    $mtime = $mtime ? "?$mtime" : '';
		}

		if (starts_with($file, $root)) {
		    $file = substr($file, strlen($root));
		    $file = start_in('/', $file);
		}

		// HACK: When installed locally, SoleCMS defines ROOT_DIR as
		// /whatever/core/ and SITE_DIR as /whatever/sites/site, not
		// /whatever/core/sites/site, so stripping ROOT_DIR off SITE_DIR
		// fails.
		if (defined('SG_VERSION')) {
		    $root = preg_replace('#/core/$#', '/', $root, -1, $count);
		    if ($count > 0 && starts_with($file, $root, false, $remainder)) {
		        return $app->URL_BASE . $remainder . $mtime;
		    }
		}

		// Fall back to just URL_BASE/file
		return $app->URL_BASE . ltrim($file, '/') . $mtime;

	}

}