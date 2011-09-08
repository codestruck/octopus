<?php

require_once(dirname(__FILE__) . '/htmlpurifier-4.3.0/library/HTMLPurifier.auto.php');

/**
 * @param $options Array of options to use to configure the purifier
 * @return A preconfigured HTMLPurifier instance.
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