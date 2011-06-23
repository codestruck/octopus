<?php
/**
 * Smarty plugin
 *
 * @package Smarty
 * @subpackage PluginsFunction
 */

/**
 * Smarty {html_image} function plugin
 *
 * Type:     function<br>
 * Name:     html_image<br>
 * Date:     Feb 24, 2003<br>
 * Purpose:  format HTML tags for the image<br>
 * Examples: {html_image file="/images/masthead.gif"}
 * Output:   <img src="/images/masthead.gif" width=400 height=23>
 *
 * @link http://smarty.php.net/manual/en/language.function.html.image.php {html_image}
 *      (Smarty online manual)
 * @author Monte Ohrt <monte at ohrt dot com>
 * @author credits to Duda <duda@big.hu>
 * @version 1.0
 * @param array $params parameters
 * Input:<br>
 *          - file = file (and path) of image (required)
 *          - height = image height (optional, default actual height)
 *          - width = image width (optional, default actual width)
 *          - basedir = base directory for absolute paths, default
 *                      is environment variable DOCUMENT_ROOT
 *          - path_prefix = prefix for path output (optional, default empty)
 * @param object $template template object
 * @return string
 * @uses smarty_function_escape_special_chars()
 */
function smarty_function_html_image($params, $template)
{
    require_once(SMARTY_PLUGINS_DIR . 'shared.escape_special_chars.php');

    $default = '';
    $ignoredims = '';
    $alt = '';
    $file = '';
    $height = '';
    $width = '';
    $extra = '';
    $prefix = '';
    $suffix = '';
    $path_prefix = '';
    $server_vars = $_SERVER;
    $basedir = isset($server_vars['DOCUMENT_ROOT']) ? $server_vars['DOCUMENT_ROOT'] : '';
    foreach($params as $_key => $_val) {
        switch ($_key) {
            case 'file':
            case 'height':
            case 'width':
            case 'dpi':
            case 'path_prefix':
            case 'basedir':
            case 'default':
            case 'ignoredims':
                $$_key = $_val;
                break;

            case 'alt':
                if (!is_array($_val)) {
                    $$_key = smarty_function_escape_special_chars($_val);
                } else {
                    throw new SmartyException ("html_image: extra attribute '$_key' cannot be an array", E_USER_NOTICE);
                }
                break;

            case 'link':
            case 'href':
                $prefix = '<a href="' . $_val . '">';
                $suffix = '</a>';
                break;

            default:
                if (!is_array($_val)) {
                    $extra .= ' ' . $_key . '="' . smarty_function_escape_special_chars($_val) . '"';
                } else {
                    throw new SmartyException ("html_image: extra attribute '$_key' cannot be an array", E_USER_NOTICE);
                }
                break;
        }
    }

    if (empty($file)) {
        trigger_error("html_image: missing 'file' parameter", E_USER_NOTICE);
        return;
    }

    if (substr($file, 0, 1) == '/') {
        $_image_path = $basedir . $file;
    } else {
        $_image_path = $file;
    }

    // custom
    if (!file_exists($_image_path)) {
        if (substr($default,0,1) == '/') {
            $_image_path = $basedir . $default;
        } else {
            $_image_path = $default;
        }


        if (!isset($default) || !file_exists($_image_path)) {
            return; // kick out if no default & no primary image.
        }

        $file = $default;
    }
    // end custom

    if (!isset($params['width']) || !isset($params['height'])) {
        if (!$_image_data = @getimagesize($_image_path)) {
            if (!file_exists($_image_path)) {
                trigger_error("html_image: unable to find '$_image_path'", E_USER_NOTICE);
                return;
            } else if (!is_readable($_image_path)) {
                trigger_error("html_image: unable to read '$_image_path'", E_USER_NOTICE);
                return;
            } else {
                trigger_error("html_image: '$_image_path' is not a valid image file", E_USER_NOTICE);
                return;
            }
        }
        if (isset($template->security_policy)) {
            if (!$template->security_policy->isTrustedResourceDir($_image_path)) {
                return;
            }
        }

        if (!isset($params['width'])) {
            $width = $_image_data[0];
        }
        if (!isset($params['height'])) {
            $height = $_image_data[1];
        }
    }

    if (isset($params['dpi'])) {
        if (strstr($server_vars['HTTP_USER_AGENT'], 'Mac')) {
            $dpi_default = 72;
        } else {
            $dpi_default = 96;
        }
        $_resize = $dpi_default / $params['dpi'];
        $width = round($width * $_resize);
        $height = round($height * $_resize);
    }

    // custom
    // Are we attempting to resize on the fly?
    $attempt_resample = false;
    if (isset($params['_r']) && $params['_r']) {


        // If current sizes don't match the target, attempt api resize
        if ((isset($params['_rwidth']) && $params['_rwidth'] != $width && $params['_rwidth'] < $width) || (isset($params['_rheight']) && $params['_rheight'] != $height && $params['_rheight'] < $height)) {

            $ignoredims = true;    // So we don't have to lookup the cached path sizes.
            $attempt_resample = true;
            $mtime = filemtime($_image_path);

            $filepath = str_replace(URL_SITE_DIR, '', $file); // Strip out site dir path so thumb api can handle the file path request
            $file = URL_PREFIX . "api/1/imagemanager/image/thumb?file={$filepath}&w={$params['_rwidth']}&h={$params['_rheight']}&action={$params['_raction
']}&{$mtime}";

        }

    }

    if (!$attempt_resample) {

        $mtime = filemtime($_image_path);
        $file = $file . '?' . $mtime;

    }

    if (isset($ignoredims)) {
        return $prefix . '<img src="'.$path_prefix.$file.'" alt="'.$alt.'" '.$extra.' />' . $suffix;
    }
    // end custom



    return $prefix . '<img src="' . $path_prefix . $file . '" alt="' . $alt . '" width="' . $width . '" height="' . $height . '"' . $extra . ' />' . $suffix;
}

?>

