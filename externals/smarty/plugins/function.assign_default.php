<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */

/**
 * Smarty {assign_default} function plugin
 *
 * Type:     function<br>
 * Name:     assign_default<br>
 * Purpose:  assign a fallback value for typical assign info to the template<br>
 * @author Matt Bain <mattb at solegraphics dot com>
 * @param Smarty
 */
function smarty_function_assign_default($_params, &$smarty)
{
    //$_params = $compiler->_parse_attrs($tag_attrs);

    if (!isset($_params['var'])) {
        $smarty->_syntax_error("assign: missing 'var' parameter", E_USER_WARNING);
        return;
    }

    if (!isset($_params['value'])) {
        $smarty->_syntax_error("assign: missing 'value' parameter", E_USER_WARNING);
        return;
    }
	
	if (!isset($_params['default'])) {
        $smarty->_syntax_error("assign: missing 'default' parameter", E_USER_WARNING);
        return;
    }
	
	// Determine if we use fallback or not
	$_value = (trim($_params['value']) != '') ? $_params['value'] : $_params['default'];


    $smarty->assign($_params['var'], $_value);
}

/* vim: set expandtab: */

?>
