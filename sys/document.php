<?php
                                                                 $usage = <<<END

octopus/document

    Generates API documentation for Octopus.

    Usage:

        octopus/document [--apigen path-to-apigen] [destination-dir]

    Options:

        --apigen path-to-apigen     Full path to the ApiGen (http://apigen.org/)
                                    installation to use to generate docs. This
                                    argument is optional as long as the ApiGen
                                    directory is in your PATH and the apigen.php
                                    file within it is marked executable.

        --help                      Displays this message.

    Octopus uses ApiGen (http://apigen.org/) to generate documentation from
    comments. The apigen directory needs to be in your PATH and the apigen.php
    file needs to be executable.

    TODO:

        - Generate site API docs (e.g., via octopus/document site)


Copyright (c) 2012 Codestruck, LLC.
Provided under the terms of the MIT license. See the LICENSE file for details.


END;


    require_once(dirname(dirname(__FILE__)) . '/includes/core.php');
    is_command_line() or die();
    bootstrap(array(
        'use_site_config' => false,
    ));

    array_shift($argv); // remove script name

    $options = array(
        'apigen' => trim(`which apigen.php`),
        'destination' => false,
    );

    while($arg = array_shift($argv)) {

        switch($arg) {

            case '--apigen':
                $options['apigen'] = array_shift($argv);
                break;

            case '--help':
                echo $usage;
                exit(1);

            default:

                if ($options['destination'] === false) {
                    $options['destination'] = $arg;
                } else {
                    echo "\n\nInvalid argument: '$arg'\n\n";
                    exit(1);
                }

        }

    }

    if (!empty($options['apigen'])) {

        if (is_dir($options['apigen'])) {
            $options['apigen'] = end_in('/', $options['apigen']) . 'apigen.php';
        }

        if (!is_file($options['apigen'])) {
            echo "\n\nInvalid ApiGen path: {$options['apigen']}\n\n";
            exit(2);
        }

    }

    if (empty($options['apigen'])) {

        echo <<<END

apigen.php not found.

Octopus uses ApiGen (http://apigen.org/) to generate documentation from
comments. The apigen directory needs to be in your PATH and the apigen.php
file needs to be marked executable.


END;
        exit(2);

    }

    if (!$options['destination']) {
        $options['destination'] = dirname(dirname(__FILE__)) . '/docs';
    }

    $command = array(
        'php',
        $options['apigen'],
        '--destination', $options['destination'],
        '--deprecated yes',
    );

    $baseDir = dirname(dirname(__FILE__)) . '/';

    $sources = array(
        $baseDir . 'controllers',
        $baseDir . 'includes',
        $baseDir . 'tests',
        $baseDir . 'sys',
    );
    foreach(glob($baseDir . 'externals/*/external.php') as $externalphp) {
        $sources[] = $externalphp;
    }


    foreach($sources as $source) {
        $command[] = '--source';
        $command[] = $source;
    }


    $command = implode(' ', $command);

    echo "\n\nExcuting: $command\n\n";
    passthru($command);
