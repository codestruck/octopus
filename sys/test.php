<?php
/**
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */

    $usage =
                                                                          <<<END

     Octopus Test Runner

     Usage:

       octopus/test [site|sys] [--help] [--include-slow|--only-slow]
                    [PHPUnit args]

    Options:

    	site | sys
    		'Site' runs site tests, 'sys' runs Octopus system tests. If not
    		specified, site tests are run (unless 'test' is called from
    		the Octopus dir).

    	--help
    		Display this message.

    	--include-slow
    		Also run tests from group 'slow', which is excluded by default.

    	--only-slow
    		Only run tests from group 'slow'

    Any other arguments are forwarded to PHPUnit.

END;

    $phpUnit = trim(`which phpunit`);

    if (!$phpUnit) {
        echo <<<END

Octopus uses PHPUnit to run tests, but it doesn't seem to be installed on your
system. To install, go here:

	http://www.phpunit.de/manual/3.6/en/installation.html


END;
        exit(1);
    }

    array_shift($argv); // remove script name

    $octopusDir = dirname(dirname(__FILE__)) . '/';
    $siteDir = dirname($octopusDir) . '/site/';

    $toTest = (strcmp(getcwd() . '/', $octopusDir) === 0) ? 'sys' : 'site';
    $includeSlow = false;
    $onlySlow = false;

    $args = array(
    	'--colors', 			// Colors are disabled by default when
    	                        // using passthr()

    	'--no-configuration',	// We configure from the command line

    );

    $extraPhpUnitArgs = array();


    foreach($argv as $arg) {

    	switch($arg) {

    		case 'site':
    		case 'sys':
    			$toTest = $arg;
    			break;

    		case '--include-slow':
    			$includeSlow = true;
    			break;

    		case '--only-slow':
    			$onlySlow = true;
    			break;

    		case '--help':
    			echo $usage;
    			exit();
    			break;

    		default:
    			$extraPhpUnitArgs[] = $arg;
    			break;
    	}

    }

    if ($onlySlow) {
    	$args[] = '--group';
    	$args[] = 'slow';
    } else if (!$includeSlow) {
    	$args[] = '--exclude-group';
    	$args[] = 'slow';
    }

    if (strcasecmp($toTest, 'site') === 0) {

    	if (!is_dir($siteDir)) {
    		echo "\n\n\tSite dir not found: {$siteDir}\n\n";
    		exit(1);
    	}

    	$testDir = $siteDir . 'tests';

    	if (!is_dir($testDir)) {
    		echo "\n\n\tSite tests dir not found: {$testDir}/\n\n";
    		exit(1);
    	}

    	$args[] = '--bootstrap';
        $args[] = $octopusDir . 'tests/bootstrap_site.php';

    	$testDir = $siteDir . 'tests';

    } else if (strcasecmp($toTest, 'sys') === 0) {

    	$args[] = '--bootstrap';
    	$args[] = $octopusDir . 'tests/bootstrap.php';
    	$testDir = $octopusDir . 'tests';

    } else {
    	echo <<<END

    	Invalid test target: $toTest

END;
		exit(1);
    }

    $phpUnitArgs = array_merge($args, $extraPhpUnitArgs);
    $phpUnitArgs[] = $testDir;
    $phpUnitArgs = str_replace(' ', '\\ ', $phpUnitArgs);
    $phpUnit .= ' ' . implode(' ', $phpUnitArgs);

    echo "\n\nRunning PHPUnit: $phpUnit\n\n";

    passthru($phpUnit);
