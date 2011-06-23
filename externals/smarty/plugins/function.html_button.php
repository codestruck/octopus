<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty {html_button} function plugin
 *
 * Type:     function<br>
 * Name:     html_button<br>
 * Date:     March 16, 2009<br>
 * Purpose:  format HTML tags for the image<br>
 * Input:<br>
 *         - file = file (and path) of image (required)
 *         - height = image height (optional, default actual height)
 *         - width = image width (optional, default actual width)
 *         - basedir = base directory for absolute paths, default
 *                     is environment variable DOCUMENT_ROOT
 *         - path_prefix = prefix for path output (optional, default empty)
 *
 * Examples: {html_image file="/images/masthead.gif"}
 * Output:   <img src="/images/masthead.gif" width=400 height=23>
 * @link http://smarty.php.net/manual/en/language.function.html.image.php {html_image}
 *      (Smarty online manual)
 * @author   Monte Ohrt <monte at ohrt dot com>
 * @author credits to Duda <duda@big.hu> - wrote first image function
 *           in repository, helped with lots of functionality
 * @version  1.0
 * @param array
 * @param Smarty
 * @return string
 * @uses smarty_function_escape_special_chars()
 */
function smarty_function_html_button($params, &$smarty)
{
    require_once(SMARTY_PLUGINS_DIR . 'shared.escape_special_chars.php');

    $alt = '';
    $file = '';
    $height = '';
    $width = '';
    $extra = '';
	$class = '';
    $prefix = '';
    $suffix = '';
	$type = '';
    $path_prefix = '';
    $server_vars = $_SERVER;
    $basedir = isset($server_vars['DOCUMENT_ROOT']) ? $server_vars['DOCUMENT_ROOT'] : '';
    foreach($params as $_key => $_val) {
        switch($_key) {
            case 'file':
            case 'height':
            case 'width':
            case 'dpi':
            case 'path_prefix':
            case 'basedir':
			case 'class':
			case 'border':
			case 'type':
			case 'default':
                $$_key = $_val;
                break;

            case 'alt':
                if(!is_array($_val)) {
                    $$_key = smarty_function_escape_special_chars($_val);
                } else {
                    trigger_error("html_button: extra attribute '$_key' cannot be an array", E_USER_NOTICE);
                }
                break;

            case 'link':
            case 'href':
                $prefix = '<a href="' . $_val . '">';
                $suffix = '</a>';
                break;

            default:
                if(!is_array($_val)) {
                    $extra .= ' '.$_key.'="'.smarty_function_escape_special_chars($_val).'"';
                } else {
                    trigger_error("html_button: extra attribute '$_key' cannot be an array", E_USER_NOTICE);
                }
                break;
        }
    }

    if (empty($file)) {
        trigger_error("html_button: missing 'file' parameter", E_USER_NOTICE);
        return;
    }

    if (substr($file,0,1) == '/') {
        $_image_path = $basedir . $file;
    } else {
        $_image_path = $file;
    }

    // MB: If the real image doesn't exit, flag it for css style output
    if (!file_exists($_image_path)) {
        if (substr($default,0,1) == '/') {
            $_image_path = $basedir . $default;
        }
        $file = $default;
    }

	// MB: Check that the image exists
	$sg_isfile = false;
	if(file_exists($_image_path)) {
		$sg_isfile = true;
	}

	if (!$sg_isfile) {

		$tagparams = array();

		if ($type == 'input') {
			$output = '<input class="sghtmlinput" type="submit" name="submit" value="'. $alt .'">';
		} else {
			$output = $prefix .'
			<span class="sghtmlbutton '. $class .'"'. $extra .'>
			  <span class="sghtmlbutton-center">'. $alt .'</span>
			  <span class="sghtmlbutton-right"></span>
			</span>' . $suffix;
		}

		return $output;
	}


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

    if(isset($params['dpi'])) {
        if(strstr($server_vars['HTTP_USER_AGENT'], 'Mac')) {
            $dpi_default = 72;
        } else {
            $dpi_default = 96;
        }
        $_resize = $dpi_default/$params['dpi'];
        $width = round($width * $_resize);
        $height = round($height * $_resize);
    }

    // MJE: inject -mTIMESTAMP into src
    $mtime = filemtime($_image_path);
    $file = $file . '?' . $mtime;

	$extraCustom = '';
	$extraCustom .= (isset($border)) ? ' border="'. $border .'"' : '';
	$extraCustom .= (isset($class)) ? ' class="'. $class .'"' : '';


	// Is it an input type?
	if ($type == 'input') {
		return '<input type="image" src="'. $path_prefix . $file .'" name="'. $name .'"'. $extra .'>';
	} else {
    	return $prefix . '<img src="'.$path_prefix.$file.'" alt="'.$alt.'" width="'.$width.'" height="'.$height.'"'.$extra . $extraCustom. ' />' . $suffix;
	}
}

/* vim: set expandtab: */

?>
