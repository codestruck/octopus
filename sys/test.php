<?php

    $usage =
                                                                          <<<END

     Octopus Test Runner

     Usage:

       octopus/test [all | sys | site ]


END;

    $testSys = null;
    $testSite = null;

    foreach($argv as $arg) {

        switch(strtolower($arg)) {

            case 'all':
                $testSys = $testSite = true;
                break;

            case 'sys':
                $testSys = true;
                break;

            case 'site':
                $testSite = true;
                break;
        }

    }

    if ($testSys === null && $testSite === null) {
        echo $usage;
        exit(1);
    }

    $phpUnit = trim(`which phpunit`);
    if (!$phpUnit) {
        echo <<<END

PHPUnit not found. Maybe install it?


END;
        exit(1);
    }

    $octopusDir = dirname(dirname(__FILE__)) . '/';

    if ($testSys) {

        $xml = $octopusDir . 'phpunit.xml';
        $testDir = $octopusDir . 'tests';

        passthru("$phpUnit --configuration \"$xml\" \"$testDir\"");
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

        $cmd .= " \"$testDir\"";

        passthru($cmd);
    }



?>