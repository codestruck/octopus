<?php

	$description = <<<END

octopus/tail

	Monitors an octopus log file for updates and displays them formatted for
	an 80-column console.

	Usage:

		> octopus/tail path/to/log/file


END;

	date_default_timezone_set(@date_default_timezone_get());

	array_shift($argv); // Remove script name

	if (empty($argv)) {
		echo $description;
		exit(1);
	}

	if (count($argv) > 1) {
		echo <<<END

Right now, octopus/tail only supports 1 file at a time.

END;
		exit(2);
	}

	define('OCTOPUS_TAIL_DELAY_IDLE', 3);
	define('OCTOPUS_TAIL_DELAY_ACTIVE', 1);

	$lastItem = 0;
	$nextDelay = OCTOPUS_TAIL_DELAY_ACTIVE;

	while(true) {

		$file = octopus_get_log_file($argv[0]);
		$lastItem = octopus_display_log($file, $lastItem);

		sleep($nextDelay);
	}

	function octopus_display_log($file, $lastItem) {

		$contents = @file_get_contents($file);
		if (!$contents) return $lastItem;

		$contents = trim($contents);
		$contents = trim($contents, ',');
		$contents = @json_decode('[' . $contents . ']', true);

		if (!$contents) {
			return $lastItem;
		}

		$newLastItem = $lastItem;

		foreach($contents as $item) {

			$itemTime = strtotime($item['time']);

			if ($itemTime > $lastItem) {
				octopus_display_log_item($item);
				$newLastItem = $itemTime;
			}

		}

		return $newLastItem;
	}

	function octopus_display_log_item($item) {

		echo <<<END

{$item['time']}

END;

		var_dump($item['message']);

		echo "\n";

	}

	function octopus_get_log_file($file) {

		return $file;

	}

?>