<?php
/**
 * @TODO Get rid of this or convert it to something named, e.g. octopus/request.
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */

     $script = array_shift($argv);

     $usage = <<<END

Usage:
        $script path/to/action arg1=value1 arg2=value2


END;

     if (!defined('STDIN')) {
         header('Status: 403 Forbidden');
         echo('<h1 style="color:red;text-align:center;">FORBIDDEN</h1>');
         exit();
     }

    if (empty($argv)) {
        echo $usage;
        exit(1);
    }

    require_once(dirname(__FILE__) . '/includes/core.php');

    bootstrap(array('command_line' => true));

    $path = array_shift($argv);
    foreach($argv as $arg) {

        if (preg_match('/^(?<key>.+?)\s*(=\s*(?<value>.*))?$/', $arg, $m)) {
            $_GET[$m['key']] = $m['value'];
        }

    }

    $app = Octopus_App::singleton();
    $response = $app->getResponse($path);
    $response->render();

