<?php

	define('BOOTSTRAP_VERSION', '2.0.4');

	/**
	 * Includes Twitter bootstrap CSS and Javascript on the current page.
	 * @param  array  $options Options for bootstrap.
	 * Options are:
	 *
	 * 	version - The version to use
	 * 	weight - Weight to use for CSS (defaults to -1000)
	 * 	responsive - Whether to include the responsive css (true by default)
	 * 	javascript - Whether to include supporting JS. If numeric, this is
	 * 	the weight to use (defaults to -500)
	 * 	jQuery - URL of jQuery to use, or false to not use it.
	 * @copyright (c) 2012 Codestruck, LLC.
	 * @license http://opensource.org/licenses/mit-license.php/
	 */
	function use_bootstrap($options = array()) {

		$defaults = array(
			'version' => BOOTSTRAP_VERSION,
			'responsive' => true,
			'javascript' => -500,
			'jquery' => '//ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js',
			'weight' => -1000,
			'minify' => true,
		);

		$options = array_merge($defaults, $options);
		$dir = "/octopus/externals/bootstrap/bootstrap-{$options['version']}/";

		$cssExt = $options['minify'] ? '.min.css' : '.css';

		add_css($dir . "css/bootstrap{$cssExt}", $options['weight']);

		if ($options['responsive']) {
			add_css($dir . "css/bootstrap-responsive{$cssExt}", $options['weight']);
		}

		if ($options['javascript'] !== false) {

			if ($options['jquery']) {
				add_javascript($options['jquery'], $options['javascript']);
			}

			$jsExt = $options['minify'] ? '.min.js' : '.js';
			add_javascript($dir . "js/bootstrap{$jsExt}", $options['javascript']);
		}

	}
