<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty {mailto} function plugin
 *
 * Type:     function<br>
 * Name:     sglink<br>
 * Date:     Jan 23rd, 2010
 * Purpose:  automate link creating and url validation.<br>
 * Input:<br>
 *         - address = e-mail address
 *         - text = (optional) text to display, default is address
 *         - encode = (optional) can be one of:
 *                * none : no encoding (default)
 *                * javascript : encode with javascript
 *                * javascript_charcode : encode with javascript charcode
 *                * hex : encode with hexidecimal (no javascript)
 *         - cc = (optional) address(es) to carbon copy
 *         - bcc = (optional) address(es) to blind carbon copy
 *         - subject = (optional) e-mail subject
 *         - newsgroups = (optional) newsgroup(s) to post to
 *         - followupto = (optional) address(es) to follow up to
 *         - extra = (optional) extra tags for the href link
 *
 * Examples:
 * <pre>
 * {sglink url="someurl.com"}
 * {sglink url="someurl.com" target="_blank" class="external"}
 * </pre>
 * 
 * @version  1.0
 * @author   Matt Bain <mattb@solegraphics.com>
 * @param    array
 * @param    Smarty
 * @return   string
 */
function smarty_function_sglink($params, &$smarty)
{
    $output = '';

    if (empty($params['url'])) {
        $smarty->trigger_error("sglink: missing 'url' parameter");
        return;
    } else {
        $link = trim($params['url']);
    }
	
	if ($link == '') {
		return;
	}

    $text = $link;
	
	$prefix = substr($link,0,4);
	
	if ($prefix != 'http' && $prefix != 'ftp:') {
		$link = 'http://' . $link;
	}

    // netscape and mozilla do not decode %40 (@) in BCC field (bug?)
    // so, don't encode it.
    $attributes = array();
    foreach ($params as $var=>$value) {
        switch ($var) {
            case 'url':
			// Do Nothing
            break;
			
			case 'text':
			$text = $value;
			break;

            default:
			$attributes[] = $var . "=\"$value\"";
        }
    }
	
	$attr = (count($attributes) > 0) ? " ". implode(" ", $attributes) : '';
	
	$output = sprintf('<a href="%s"%s>%s</a>',$link,$attr,$text);
	
	return $output;

}

/* vim: set expandtab: */

?>
