<?php

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_Html_Page_DefaultFileFinder {

	private $app;

	public function __construct($app = null) {
		$this->app = $app;
	}

	public function findFile($file, Octopus_Html_Page $page) {

		$app = $this->app ? $this->app : Octopus_App::singleton();
		if (!$app) return false;

		$dirs = array(
			'root' => $app->ROOT_DIR,
			'theme' => null,
			'site' => $app->SITE_DIR,
			'octopus' => $app->OCTOPUS_DIR,
		);

		$resp = $app->getCurrentResponse();
		if ($resp) {

			$theme = $resp->theme;
			if ($theme) {
				$dirs['theme'] = $dirs['site'] . 'themes/' . $theme . '/';
			}

		}

		foreach($dirs as $dir) {

			if (!$dir) continue;

			$candidate = $dir . ltrim($file, '/');
			if (is_file($candidate)) {
				return $candidate;
			}

		}

		return false;

	}

}