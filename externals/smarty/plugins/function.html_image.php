<?php

require_once(dirname(__FILE__) . '/function.image.php');

/**
 * Shim for supporting old {html_image} style calls. See function.image.php
 */
function smarty_function_html_image($params, $template) {
	return smarty_function_image($params, $template);
}

