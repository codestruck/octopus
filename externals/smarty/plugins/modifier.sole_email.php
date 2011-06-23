<?php
/**
 * Smarty shared plugin
 * @package Smarty
 * @subpackage plugins
 * @author Matt Bain - http://www.solegraphics.com
 * @date August 19th, 2009
 */


/**
 * Function: smarty_sole_email
 * Purpose:  A little spam proofing for email address'
 * Smarty Sample: {$content|sole_email}
 * @param string
 * @return string
 */
function smarty_modifier_sole_email($content, $link=false) {

    if (empty($content)) {
   		return '';
    }

    // replace single quotes in a tags
    $s = '/(<a[^>]*?>[^\'>]*?)\'([^\']*?<\/a>)/';
    $r = '$1&rsquo;$2';
    $content = preg_replace($s, $r, $content);

    // replace mailtos with emails in the link
    $s = '/<a[^>]*href="mailto: *([^@ <>:"\']+)@([^ <>\.:"\']+)\.([^ <>:"\']+) *">([^@ <>:"\']+@[^ <>:"\']+)<\/a>/';
    $r = '<span rel="sgSafeSend" one="$1" two="$2" three="$3"></span>';

    $content = preg_replace($s, $r, $content);

    // replace mailtos with non-emails in the link
    $s = '/<a[^>]*href="mailto: *([^@ <>:"\']+)@([^ <>\.:"\']+)\.([^ <>:"\']+) *">(.*?)<\/a>/';
    $r = '<span rel="sgSafeSendExtended" one="$1" two="$2" three="$3" four="$4"></span>';

    $content = preg_replace($s, $r, $content);

    if ($link) {
        $s = '/\b([^@ <>:"\']+)@([^ <>\.:"\']+)\.([^ <>:"\']+)\b/';
        $r = '<span rel="sgSafeSend" one="$1" two="$2" three="$3"></span>';
    } else {
        // replace emails in plain text
        $s = '/\b([^@ <>:"\']+)@([^ <>\.:"\']+)\.([^ <>:"\']+)\b/';
        $r = '<span rel="sgSafeSendPlain" one="$1" two="$2" three="$3"></span>';
    }

    $content = preg_replace($s, $r, $content);

    return $content;

}