<?php
require_once($EXTERNAL_DIR . 'spyc-0.4.5/spyc.php');

/**
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
function load_yaml($file) {
    return spyc_load_file($file);
}

/**
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
function load_yaml_string($yaml) {
    return spyc_load($yaml);
}

