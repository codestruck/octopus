<?php
                                                                 $usage = <<<END

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

Copyright (c) 2012 Codestruck, LLC.
Provided under the terms of the MIT license. See the LICENSE file for details.


END;

    require_once(dirname(dirname(__FILE__)) . '/includes/core.php');
    is_command_line() or die();
    bootstrap(array(
        'use_site_config' => false,
    ));

    array_shift($argv); // Remove script name

    $monitor = new Octopus_Log_Monitor();
    $added = 0;
    $fullStackTraces = false;
    $watching = array();

    if (count($argv) > 0) {

        while($arg = array_shift($argv)) {

            switch($arg) {

                case '--full-stack':
                case '--full-stack-trace':
                case '--full-stack-traces':
                    $fullStackTraces = true;
                    break;

                case '--help':
                    echo       $usage;
                    exit();
                    break;

                default:

                    if (is_dir($arg)) {
                        $monitor->addDirectory($arg);
                        $watching[] = $arg;
                    } else {
                        $monitor->addFile($arg);
                        $watching[] = $arg;
                    }
                    break;
            }

        }

    }

    if (empty($watching)) {

        // nothing == monitor LOG_DIR
        $dir = dirname(dirname(dirname(__FILE__))) . '/_private';
        $monitor->addDirectory($dir);
        $watching[] = $dir;

    }

    echo "\nMonitoring:";
    foreach($watching as $w) {
        echo "\n\t$w";
    }
    echo "\n\n";

    $firstTime = true;
    $lastFailure = null;

    while(true) {

        $failure = Octopus_Log_Listener_File::getLastWriteFailure();

        if ($failure) {

            if (serialize($failure) !== serialize($lastFailure)) {

                echo <<<END

********************************************************************************
It looks like Octopus is having trouble writing to log files-- this is usually
because of a permissions/ownership issue on the _private or _private/log
directories. You should check those.
********************************************************************************

END;

            }

            $lastFailure = $failure;

        }


        if ($items = $monitor->poll()) {
            $nextDelay = 1;
        } else {
            $nextDelay = 3;
        }

        $now = time();

        // Don't display any items the first time through - this prevents a
        // million items from scrolling past when you start octopus/tail
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


