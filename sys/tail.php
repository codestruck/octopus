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

        --mail
            Automatically open email send by Octopus in your default browser.

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
    $openMail = false;
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

                case '--mail':
                    $openMail = true;
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

    if ($openMail) {
        echo "Watching for emails to open...";
        echo "\n\n";
    }

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

                $console = new Octopus_Log_Listener_Console(false);
                $console->stackTraceLines = $fullStackTraces ? -1 : 1;
                $console->renderInColor = true;

                $text = $console->formatForDisplay(
                    $item['message'],
                    $item['log'],
                    $item['level'],
                    is_numeric($item['time']) ? $item['time'] : strtotime($item['time']),
                    $item['trace'],
                    true
                );

                $fp = fopen('php://stderr', 'w');
                if ($fp) {
                    fputs($fp, $text);
                    fclose($fp);
                }

                if ($openMail && $item['log'] === 'emails.log') {
                    octopus_open_mail($item['message']);
                }

            }

        }

        $firstTime = false;
        sleep($nextDelay);
    }


function octopus_open_mail($message) {

    if (!is_array($message)) {
        return;
    }

    $file = sys_get_temp_dir() . '/' . md5(serialize($message)) . '.html';

    foreach($message as $key => $value) {
        if ($key !== 'body_html') {
            $message[$key] = h($value);
        }
    }

    $replyTo = '';
    if (!empty($message['reply-to'])) {
        $replyTo = "(Reply to: {$message['reply-to']})";
    }

    $time = date('r');

    $message['body_text'] = h($message['body_text']);

    file_put_contents(
        $file,
        <<<END
<div style="background: #e9e9e9; margin-bottom: 20px; padding: 20px;">
    <div style="color: #333; font-family: monospace; font-size: 14px; margin: 0 auto; width: 600px;">
        From: {$message['from']} $replyTo
        <br />
        To: {$message['to']}
        <br />
        Subject: {$message['subject']}
        <br />
        Sent: $time
    </div>
</div>

{$message['body_html']}


<div style="margin: 20px auto; padding: 20px; width: 600px; font-family: monospace;">
    <div style="border-top: 1px solid #666; white-space: pre;">
{$message['body_text']}
    </div>
</div>

END
    );

    exec("open \"$file\"");




}