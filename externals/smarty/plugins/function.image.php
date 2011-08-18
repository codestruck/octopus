<?php

Octopus::loadClass('Octopus_Image');
Octopus::loadClass('Octopus_Html_Element');

/**
 * Smarty plugin
 *
 * @package Smarty
 * @subpackage PluginsFunction
 */

/**
 * Enhanced html_image using octopus core stuff.
 */
function smarty_function_image($params, $template)
{
    $imageAttrs = array();
    $linkAttrs = array();
    $missingAttrs = array();

    $baseDir = null;

    $resize = false;
    // $crop = false;

    foreach($params as $key => $value) {

        if ($key === 'file') {
            // file == src
            $imageAttrs['src'] = $value;
        } else if ($key === 'href') {
            $linkAttrs['href'] = $value;
        } else if ($key === 'resize') {
            $resize = $value;
        } else if ($key === 'basedir') {
            $baseDir = $value;
        } else if (starts_with($key, 'link-')) {
            $linkAttrs[substr($key, 5)] = $value;
        } else if (starts_with($key, 'missing-')) {
            $key = substr($key, 8);
            if ($key === 'file') $key = 'src';
            $missingAttrs[$key] = $value;
        } else {
            $imageAttrs[$key] = $value;
        }

    }

    if (empty($imageAttrs['src'])) {
        throw new SmartyException("file or src attribute must be specified for {image}.", E_USER_NOTICE);
    }

    if (!$baseDir) {

        if (defined('ROOT_DIR')) {
            $baseDir = ROOT_DIR;
        } else if (isset($_SERVER['DOCUMENT_ROOT'])) {
            $baseDir = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/';
        } else {
            $baseDir = '';
        }
    }


    // Resolve where the image actually is
    $file = _octopus_smarty_find_image($imageAttrs['src'], $baseDir, $template);

    if (!$file) {
        
        if (empty($missingAttrs)) {
            return '';
        }

        $missingContent = _octopus_smarty_get_missing_image_content($imageAttrs, $linkAttrs, $missingAttrs, $file);


        if (!isset($missingAttrs['class'])) {
            $missingAttrs['class'] = 'missing';
        }

        if (isset($missingAttrs['src'])) {
            
            // Render a placeholder image
            $file = _octopus_smarty_find_image($missingAttrs['src'], $baseDir, $template);

            if (!$file) {
                throw new SmartyException("Could not find image to use when missing: '{$missingAttrs['src']}'.");
            }

            $resize = $crop = false;
            $imageAttrs = array_merge($imageAttrs, $missingAttrs);

        } else {

            // Render a <span>
            $span = new Octopus_Html_Element('span', $missingAttrs);
            return $span->render(true);
        }

    }

    if ($resize) {

        $width = isset($imageAttrs['width']) ? $imageAttrs['width'] : null;
        $height = isset($imageAttrs['height']) ? $imageAttrs['height'] : null;
        $file = _octopus_smarty_resize_image($file, $width, $height, $imageAttrs);
        $imageAttrs['width'] = $width;
        $imageAttrs['height'] = $height;

    } 
    /*
    TODO: implement cropping
    else if ($crop) {
    }
    */
    else {

        $size = @getimagesize($file);

        if ($size) {
            if (!isset($imageAttrs['width'])) $imageAttrs['width'] = $size[0];
            if (!isset($imageAttrs['height'])) $imageAttrs['height'] = $size[1];
        } else {
            throw new SmartyException("Could not get size of image file: '{$imageAttrs['src']}.");
        } 

        $file = _octopus_smarty_get_file_url($file, $baseDir);
    }

    if (isset($template->security_policy)) {
        if (!$template->security_policy->isTrustedResourceDir($file)) {
            return _octopus_smarty_missing_image($linkAttrs, $imageAttrs, $missingAttrs);
        }
    }

    $img = new Octopus_Html_Element('img', $imageAttrs);
    
    if (!empty($linkAttrs)) {
        $link = new Octopus_Html_Element('a', $linkAttrs);
        $link->append($img);
        return $link->render(true);
    }

    return $img->render(true);
}

/**
 * Given an arbitrary image source, returns the physical path to the image file,
 * or false if it can't be found.
 */ 
function _octopus_smarty_find_image($src, $baseDir, $template) {
    
    /* Cases Handled:
     *  
     * 1. src is physical path to image (/var/www/images/whatever.gif)
     * 2. src is absolute path to image in the app (e.g. /site/images/whatever.gif
     *    or  /site/octopus/images/whatever.gif).
     * 3. src is an absolute path, that when prefixed with SITE_DIR, resolves to an image
     *    (e.g., /images/whatever.gif becomes /site/images/whatever.gif).
     * 4. src is an absolute path, that when prefixed with OCTOPUS_DIR, resolves to an image
     *    (e.g., /images/whatever.gif becomes /octopus/images/whatever.gif).
     * 4. src is relative to the template directory
     */

    $src = trim($src);
    if (!$src) return false;

    if (!starts_with($src, '/')) {

        // Relative path == relative to the template dir
        $templateDir = $template->getTemplateFilepath();
        if (!$templateDir) return false;

        $file = rtrim($templateDir, '/') . '/' . $src;

        return is_file($file) ? $file : false;
    }

    if (is_file($src)) {
        return $src;   
    }

    if ($baseDir && !starts_with($src, $baseDir)) {
        $file = rtrim($baseDir, '/') .  $src;
        if (is_file($file)) {
            return $file;
        }
    }

    // Look in site dir
    if (defined('SITE_DIR')) {
        $file = rtrim(SITE_DIR, '/') . $src;
        if (is_file($file)) {
            return $file;
        }
    }

    // Look in octopus dir
    if (defined('OCTOPUS_DIR')) {
        $file = rtrim(OCTOPUS_DIR, '/') . $src;
        if (is_file($file)) {
            return $file;
        }
    }
        
    return false;
}

function _octopus_smarty_get_file_url($file) {
    
    /* Cases Handled:
     * 
     * 1. $file starts with SITE_DIR
     * 2. $file starts with OCTOPUS_DIR
     * 3. $file starts with ROOT_DIR
     */

    $rootDir = defined('ROOT_DIR') ? ROOT_DIR : (isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '');
    $originalFile = $file;

    foreach(array('SITE_DIR', 'OCTOPUS_DIR', 'ROOT_DIR') as $dir) {
        
        if (!defined($dir)) {
            continue;
        }

        $constant = $dir;
        $dir = constant($dir);

        dump_r($constant);
        dump_r($dir);
        exit();

        if (!starts_with($file, $dir)) {
            continue;
        }

        $file = ltrim(substr($file, strlen($dir)), '/');

        if (starts_with($dir, $rootDir)) {
            $dir = rtrim(substr($dir, strlen($rootDir)), '/');
        }

        if (defined('URL_BASE')) {
            $urlBase = URL_BASE;
        } else if (is_callable('find_url_base')) {
            $urlBase = find_url_base();
        } else {
            $urlBase = '/';
        }

        return $urlBase . ltrim($dir, '/') . '/' . $file;
    }

    throw new SmartyException("File not accessible via HTTP: '$originalFile'");
}

/**
 * On-the-fly resizes an image and returns the physical path of the resized image file.
 */
function _octopus_smarty_resize_image($file, $width, $height, &$imageAttrs) {
    
    if ($width === null && $height === null) {
        return $file;
    }

    // Calculate new dimensions dynamically
    $size = @getimagesize($file);

    if (!$size) {
        throw new SmartyException("Could not get size of image file: '{$imageAttrs['src']}.");
    } 

    list($originalWidth, $originalHeight) = $size;

    if ($width === null) {

        if ($height == $originalHeight) {
            $width = $originalWidth;
        } else {
            $width = round(($height / $originalHeight) * $originalWidth);
        }
    } else if ($height === null) {
        
        if ($width == $originalWidth) {
            $height = $originalHeight;
        } else {
            $height = round(($width / $originalWidth) * $originalHeight);
        }

    }

    if ($width == $originalWidth && $height == $originalHeight) {
        return $file;
    }

    /* Cached file name uses:
     *
     * 1. Mod time of original file
     * 2. 'r'
     * 3. md5 of original file name + mod time
     * 4. widthxheight
     *
     * By putting the mod time first, it's really easy to purge old 
     * files from the cache.
     */

    $info = pathinfo($file);
    $mtime = filemtime($file);
    $hash = md5($file . $mtime);

    $cacheName = "$mtime_r_$hash_$widthx$height.{$info['extension']}";
    $cacheDir = PRIVATE_DIR . 'smarty_image/';
    $cacheFile = $cacheDir . $cacheName;

    if (is_file($cacheFile)) {
        return $cacheFile;
    }

    // Not in cache, so do some resizing.
    $i = new Octopus_Image(array(
        'action' => 'r',
        'width' => $width,
        'height' => $height,
        'mod' => ''
    ));

    $i->processImages($cacheDir, $cacheName, $file);

    return $cacheFile;
}

?>

