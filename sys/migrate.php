<?php

	require_once(dirname(dirname(__FILE__)) . '/includes/core.php');

	if (!is_command_line()) {
		die();
	}

	bootstrap();

	$siteDir = dirname(dirname(dirname(__FILE__))) . '/site/';

	foreach(glob($siteDir . 'models/*.php') as $f) {

		$model = basename($f, '.php');

		echo "\nMigrating $model\n";

		Octopus_DB_Schema_Model::makeTable($model);

	}

	echo "\nMigration complete!\n\n";

?>