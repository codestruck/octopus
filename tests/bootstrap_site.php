<?php

	/**
	 * This file is included by the Octopus test runner when executing the
	 * site's tests (in site/tests).
	 */

	define('OCTOPUS_TESTING_SITE', true);

	require_once(dirname(__FILE__) . '/bootstrap.php');

	if (is_file(SITE_DIR . 'tests/bootstrap.php')) {
		Octopus::requireOnce(SITE_DIR . 'tests/bootstrap.php');
	}

