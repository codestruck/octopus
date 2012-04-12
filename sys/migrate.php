<?php

	require_once(dirname(dirname(__FILE__)) . '/includes/core.php');

	if (!is_command_line()) {
		die();
	}

	array_shift($argv); // Remove script name

	// Support host name via command line
	if (!empty($argv)) {
		$_SERVER['HTTP_HOST'] = array_shift($argv);
	}

	bootstrap();

	$siteDir = dirname(dirname(dirname(__FILE__))) . '/site/';

	foreach(glob($siteDir . 'models/*.php') as $f) {

		$model = basename($f, '.php');

		echo "\nMigrating model $model...\n";

		try {
			Octopus_DB_Schema_Model::makeTable($model);
		} catch(Octopus_DB_Exception $ex) {

			$app = Octopus_App::singleton();

			// TODO: Use a more specific exception
			if (strpos($ex->getMessage(), "DB configuration") !== false) {
				echo <<<END
--------------------------------------------------------------------------------

	No DB configuration exists for hostname: {$app->getHostname()}

	To specify a different hostname, provide it as an argument to
	octopus/migrate:

		> octopus/migrate my.host.name.com

--------------------------------------------------------------------------------

END;
				exit(1);
			}

			throw $ex;

		}

	}

	echo "\nMigration complete!\n\n";

?>