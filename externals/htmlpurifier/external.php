<?php

Octopus::requireOnce(dirname(__FILE__) . '/htmlpurifier-4.4.0/HTMLPurifier.standalone.php');

/**
 * @param $options Array of options to use to configure the purifier
 * @return A preconfigured HTMLPurifier instance.
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
function get_html_purifier($options = array()) {

    $dir = get_option('OCTOPUS_PRIVATE_DIR') . 'htmlpurifier';
    if (!is_dir($dir)) {
    	mkdir($dir);
    }

	$config = HTMLPurifier_Config::createDefault();
	$config->set('Cache.SerializerPath', $dir);

	foreach($options as $key => $value) {
		$config->set($key, $value);
	}

    return new HTMLPurifier($config);
}