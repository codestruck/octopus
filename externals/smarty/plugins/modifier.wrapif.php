<?php
/**
 * Smarty shared plugin
 * @package Smarty
 * @subpackage plugins
 * @author Matt Bain - http://www.solegraphics.com
 * @date August 19th, 2009
 */


/**
 * Function: smarty_wrapif
 * Purpose:  Used to help format a string assuming it's not empty
 * Options:  enter "wrap" as any html and %STR% where you want the original string to be inserted
 * Smarty Sample: {$string|wrapif:'<a href="%STR%">%STR</a>'} - Conditionally make a link
 * Smarty Sample: {$string|wrapif:"string":"":"<br />"} - Add a linebreak after, only if string isn't empty
 * @param string
 * @return string
 */
function smarty_modifier_wrapif($string, $wrap, $after='') {
   
	if (!empty($string)) {
	  $value = ($wrap == '') ? $string : str_replace("%STR%",$string,$wrap);	
	  $value .= ($after != '') ? $after : '';
	  
	  return $value;
	  
	}
	  
	return '';
	
}  