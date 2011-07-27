<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty remove_tags modifier plugin
 *
 * Type:     modifier<br>
 * Name:     strip_tags<br>
 * Purpose:  strip html tags from text
 * @link http://smarty.php.net/manual/en/language.modifier.remove.tags.php
 *          remove_tags (Smarty online manual)
 * @author   Matt Bain <mattb at solegraphics dot com>
 * @param string
 * @param boolean
 * @return string
 */
function smarty_modifier_remove_tags($string, $tags)
{
    
	if ($tags != '') {
		return preg_replace('@<('. $tags .')[^>]*?>.*?</\1>@siu','',$string);
	}
	
}

/* vim: set expandtab: */

?>
