<?php
                                                                 $usage = <<<END

octopus/log

    Outputs the contents of one or more Octopus-generated log file, formatted
    for an 80-column console.

    Usage:

        octopus/log [path/to/log/file | path/to/log/dir] [options]

    If no log file / directory is specified, the _private directory is
    searched for log files.

    Options:

        --full-stack
            Display full stack traces, rather than just the line that
            triggered the log event.

        --since <time>
            Only display items that have happened since <time>. <time> can be
            any string that strototime accepts.

        --recent
            Only shows items from the last 15 minutes.

        --help
            Displays this message.

Copyright (c) 2012 Codestruck, LLC.
Provided under the terms of the MIT license. See the LICENSE file for details.


END;

    require_once(dirname(dirname(__FILE__)) . '/includes/core.php');
    is_command_line() or die();
    bootstrap(array(
        'use_site_config' => false,
    ));

    array_shift($argv); // remove script name

    $items = array();
    $hasErrors = false;

    $console = new Octopus_Log_Listener_Console(false);
    $width = 80;
    $console->stackTraceLines = 1;
    $console->renderInColor = true;

    $since = 0;
    $fileOrDirCount = 0;

    while($arg = array_shift($argv)) {

        switch($arg) {

            case '--full-stack':
            case '--full-stack-trace':
            case '--full-stack-traces':
                $console->stackTraceLines = -1;
                break;

            case '--since':

                if (empty($argv)) {
                    echo "\n\nERROR: --since requires a time\n\n";
                    exit(1);
                }

                $sinceArg = array_shift($argv);
                $since = strtotime($sinceArg);
                if ($since === false) {
                    echo "\n\nERROR: Invalid argument to --since\n\n";
                    exit(1);
                }

                break;

            case '--recent':
                $since = time() - (60 * 15);
                break;

            case '--help':
                echo $usage;
                exit();
                break;

            default:

                $fileOrDirCount++;

                if (!_octopus_read_log_items($arg, $items)) {
                    echo <<<END

Invalid log file / dir: $arg

END;
                    $hasErrors = true;
                }

                break;

        }

    }

    if ($hasErrors) {
        exit(1);
    }

    if (!$fileOrDirCount) {

        // By default, search _private for log files
        if (!defined('OCTOPUS_PRIVATE_DIR') || !is_dir(OCTOPUS_PRIVATE_DIR)) {
            echo "\n\nERROR: No log files/dirs specified and OCTOPUS_PRIVATE_DIR does not exist.\n\n";
            exit(1);
        }

        _octopus_read_log_dir_items(OCTOPUS_PRIVATE_DIR, $items);

    }

    if ($since) {

        $filtered = array();
        foreach($items as $item) {
            if ($item['time'] >= $since) {
                $filtered[] = $item;
            }
        }
        $items = $filtered;

    }

    usort($items, array('Octopus_Log', 'compareLogItems'));
    $count = 0;
    $logFiles = array();

    foreach($items as $item) {

        $logFiles[$item['_octopus_log_file']] = true;

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

        $count++;

    }

    $items = plural_count($count, 'item');
    $files = plural_count(count($logFiles), 'file');

    echo <<<END

{$items} found in {$files}


END;

////////////////////////////////////////////////////////////////////////////////
//
// Helper Functions
//
////////////////////////////////////////////////////////////////////////////////

    function _octopus_read_log_items($fileOrDir, Array &$items) {

        return
            (is_file($fileOrDir) && _octopus_read_log_file_items($fileOrDir, $items)) ||
            (is_dir($fileOrDir) && _octopus_read_log_dir_items($fileOrDir, $items));

    }

    function _octopus_read_log_file_items($file, Array &$items) {

        $fileItems = Octopus_Log_Listener_File::readFile($file);
        if ($fileItems === false) {
            return false;
        }

        foreach($fileItems as $item) {

            $id = empty($item['id']) ? md5(serialize($item)) : $item['id'];
            $item['_octopus_log_file'] = $file;
            $items[$id] = $item;
        }

        return true;

    }

    function _octopus_read_log_dir_items($dir, Array &$items) {

        $dir = rtrim($dir, '/') . '/';
        $handle = opendir($dir);

        if (!$handle) {
            return false;
        }

        while(($item = readdir($handle)) !== false) {

            if ($item === '.' || $item === '..') {
                continue;
            }

            $file = $dir . $item;

            if (is_file($file)) {

                if (preg_match('/(\.\d+)?\.log$/', $item)) {
                    _octopus_read_log_file_items($dir . $item, $items);
                }

            } else if (is_dir($file)) {
                _octopus_read_log_dir_items($file, $items);
            }

        }

        closedir($handle);
        return true;

    }
