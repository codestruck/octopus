<?php

    require_once(dirname(dirname(__FILE__)) . '/includes/core.php');
    is_command_line() or die();
    bootstrap(array(
    	'use_site_config' => false,
    ));

    $description = <<<END

octopus/tail

    Monitors one or more Octopus log files for updates and displays them
    formatted for an 80-column console.

    Usage:

        > octopus/tail [path/to/log/dir | /path/to/log/file] [options]

    If no log directory or file is specified, the _private directory of the
    current Octopus app is assumed.

    Options:

        --full-stack
            Display full stack traces, rather than just the line that
            triggered the log event.

        --help
            Display this message.


END;

    array_shift($argv); // Remove script name

    $monitor = new Octopus_Log_Monitor();
    $added = 0;
    $fullStackTraces = false;

    if (count($argv) > 0) {

        while($arg = array_shift($argv)) {

            switch($arg) {

                case '--full-stack':
                case '--full-stack-trace':
                case '--full-stack-traces':
                    $fullStackTraces = true;
                    break;

                case '--help':
                    echo $description;
                    exit();
                    break;

                default:

                    if (is_dir($arg)) {
                        $monitor->addDir($arg);
                        $added++;
                    } else {
                        $monitor->addFile($arg);
                        $added++;
                    }
                    break;
            }

        }

    }

    if (!$added) {

        // nothing == monitor LOG_DIR
        $dir = dirname(dirname(dirname(__FILE__))) . '/_private';
        $monitor->addDirectory($dir);
        echo "\nMonitoring $dir\n\n";

    }

    $firstTime = true;

    while(true) {

        if ($items = $monitor->poll()) {
            $nextDelay = 1;
        } else {
            $nextDelay = 3;
        }

        $now = time();

        // Don't display any items the first time through
        if (!$firstTime) {

	        foreach($items as $item) {
	            octopus_display_log_item($item);
	        }

        }

    	$firstTime = false;
        sleep($nextDelay);
    }


    function octopus_display_log_item($item, $width = 80) {

        global $fullStackTraces;

        $console = new Octopus_Log_Listener_Console(false);
        $console->stackTraceLines = $fullStackTraces ? -1 : 1;
        $console->renderInColor = true;

        $text = $console->formatForDisplay(
            $item['message'],
            $item['log'],
            $item['level'],
            is_numeric($item['time']) ? $item['time'] : strtotime($item['time']),
            $item['trace'],
            true,
            $width
        );

        $fp = fopen('php://stderr', 'w');
        if ($fp) {
            fputs($fp, $text);
            fclose($fp);
        }

    }


