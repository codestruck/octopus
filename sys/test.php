<?php

    $usage =
                                                                          <<<END

     Octopus Test Runner

     Usage:

       octopus/test [all | sys | site ]


END;

    $testSys = null;
    $testSite = null;

    array_shift($argv); // remove script name
    $what = strtolower(array_shift($argv));

    $testSys = ($what === 'sys' || $what === 'all');
    $testSite = ($what === 'site' || $what === 'all');

    if (!($testSys || $testSite)) {
        $testSite = true;
        array_unshift($argv, $what);
    }

    $extraArgs = implode(' ', $argv);

    $phpUnit = trim(`which phpunit`);
    if (!$phpUnit) {
        echo <<<END

PHPUnit not found. Maybe install it?


END;
        exit(1);
    }

    // Using passthru, colors are not used by default
    $phpUnit = "$phpUnit --colors";

    $octopusDir = dirname(dirname(__FILE__)) . '/';

    if ($testSys) {

        $xml = $octopusDir . 'phpunit.xml';
        $testDir = $octopusDir . 'tests';

        passthru("$phpUnit --configuration \"$xml\" $extraArgs \"$testDir\"");
    }

    if ($testSite) {

        $siteDir = dirname($octopusDir) . '/site/';
        $testDir = $siteDir . 'tests/';


        if (!is_dir($testDir)) {

            echo <<<END

There is no tests/ directory in site/ (looking at $testDir)


END;
            exit(1);
        }

        $xml = $siteDir . 'phpunit.xml';
        if (!is_file($xml)) {

            $xml = false;

            $bootstrap = $testDir . 'bootstrap.php';
            if (!is_file($bootstrap)) {
                $bootstrap = $octopusDir . 'tests/bootstrap_site.php';
            }
        }

        $cmd = $phpUnit . ' ';

        if ($xml) {
            $cmd .= "--configuration \"$xml\"";
        } else {
            $cmd .= "--bootstrap \"$bootstrap\"";
        }

        $cmd .= " $extraArgs \"$testDir\"";

        passthru($cmd);
    }



?>