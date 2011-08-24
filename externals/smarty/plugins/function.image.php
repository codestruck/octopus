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

    $baseDir = null; // This isn't really necessary, ROOT_DIR, SITE_DIR, and OCTOPUS_DIR, all get searched for images

    $resize = false;
    // $crop = false;

    $failIfMissing = false;
    $shimAttrs = null;

    foreach($params as $key => $value) {

        // Smarty does not allow - in attributes :-(
        $key = str_replace('_', '-', $key);

        // Old-style {html_image} shim
        switch($key) {
            case '-r':
            case '-rwidth':
            case '-rheight':
                 if ($shimAttrs === null) $shimAttrs = array();
                $shimAttrs[$key] = $value;
                $key = null;
        }

        if (!$key) {
            continue;
        }

        if ($key == 'file') {
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
        } else if ($key == 'fail-if-missing') {
            $failIfMissing = $value;
        } else if ($key == 'default') {
            $missingAttrs['src'] = $value;
        } else {
            $imageAttrs[$key] = $value;
        }

    }

    if (empty($imageAttrs['src'])) {
        throw new SmartyException("file or src attribute must be specified for {image}.", E_USER_NOTICE);
    }

    // Map old-style {html_image} resizing commands to the new way
    if (!empty($shimAttrs)) {
        if (!empty($shimAttrs['-r'])) {
            // old-style resize
            $resize = true;
        }

        if ($resize) {

            if (!empty($shimAttrs['-rwidth'])) {
                $imageAttrs['width'] = $shimAttrs['-rwidth'];
            }

            if (!empty($shimAttrs['-rheight'])) {
                $imageAttrs['height'] = $shimAttrs['-rheight'];
            }
        }
    }

    $urlBase = null;
    $dirs = _octopus_smarty_get_directories($baseDir, $urlBase);

    // Resolve where the image actually is
    $file = _octopus_smarty_find_image($imageAttrs['src'], $dirs, $template);

    if (!$file) {

        if ($failIfMissing) {

            $tries = array();
            _octopus_smarty_find_image($imageAttrs['src'], $dirs, $template, $tries);

            throw new SmartyException("Image file not found: '{$imageAttrs['src']}'. Tried: " . implode(', ', $tries));
        }

        if (empty($missingAttrs)) {
            return '';
        }

        /* Ways placeholder stuff is handled:
         *
         * 1. If 'src' attribute present, use <img>
         * 2. Otherwise, render a span
         */

        if (!isset($missingAttrs['class'])) {
            $missingAttrs['class'] = 'missing';
        }

        if (isset($missingAttrs['src'])) {

            $file = _octopus_smarty_find_image($missingAttrs['src'], $dirs, $template);

            if (!$file) {
                if ($failIfMissing) {
                    throw new SmartyException("Could not find image to use when missing: '{$missingAttrs['src']}'.");
                } else {
                        return '';
                }
            }

            $imageAttrs = array_merge($imageAttrs, $missingAttrs);

        } else {

            // Just return an empty span
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
    }

    if (isset($template->security_policy)) {
        if (!$template->security_policy->isTrustedResourceDir($file)) {
            // TODO: Exception? or Missing?
            return '';
        }
    }

    $imageAttrs['src'] = _octopus_smarty_get_file_url($file, $dirs, $urlBase);

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
function _octopus_smarty_find_image($src, $dirs, $template, &$tries = null) {

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
        if ($tries !== null) $tries[] = $file;

        return is_file($file) ? $file : false;
    }

    if (is_file($src)) {
        return $src;
    }
    if ($tries !== null) $tries[] = $src;

    foreach($dirs as $dir) {

        $file = $dir . $src;
        if (is_file($file)) {
            return $file;
        }
        if ($tries !== null) $tries[] = $file;

    }

    return false;
}

function _octopus_smarty_get_file_url($file, $dirs, $urlBase = null, $includeModTime = true) {

    /* Cases Handled:
     *
     * 1. $file starts with SITE_DIR
     * 2. $file starts with OCTOPUS_DIR
     * 3. $file starts with ROOT_DIR
     */

    $originalFile = $file;

    $soleCmsRootDirHack = null;

    foreach($dirs as $key => $dir) {
    
        if (!starts_with($file, $dir)) {
            continue;
        }

        $file = ltrim(substr($file, strlen($dir)), '/');


        if (starts_with($dir, $dirs['ROOT_DIR'])) {
            $dir = rtrim(substr($dir, strlen($dirs['ROOT_DIR'])), '/');
        } else if (defined('SG_VERSION')) {
            // HACK: In SoleCMS, ROOT_DIR can end in /core/, but SITE_DIR is /sites/ rather than /core/sites/
            if (!$soleCmsRootDirHack) {
                $soleCmsRootDirHack = preg_replace('#/core/$#', '', $dirs['ROOT_DIR']);
            }
            if (starts_with($dir, $soleCmsRootDirHack)) {
                $dir = rtrim(substr($dir, strlen($soleCmsRootDirHack)), '/');
            }            
        }

        if (!$urlBase) {

            if (defined('URL_BASE')) {
                $urlBase = URL_BASE;
            } else if (is_callable('find_url_base')) {
                $urlBase = find_url_base();
            } else {
                $urlBase = '/';
            }

        }

        $dir = ltrim($dir, '/');
        if ($dir) $dir .= '/';

        $url = $urlBase . $dir . $file;

        if ($includeModTime) {
            $url .= '?' . filemtime($originalFile);
        }

        return $url;
    }

    throw new SmartyException("File not accessible via HTTP: '$originalFile'");
}

function &_octopus_smarty_get_directories($baseDir = null, &$urlBase) {

    $dirs = array();

    foreach(array('SITE_DIR', 'OCTOPUS_DIR', 'ROOT_DIR') as $opt) {
        $dir = get_option($opt);
        if ($dir) $dirs[$opt] = $dir;
    }

    if ($baseDir) {
        $dirs['ROOT_DIR'] = rtrim($baseDir, '/') . '/';
    }

    return $dirs;
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

    if (round($width) == round($originalWidth) && round($height) == round($originalHeight)) {
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

    $cacheDir = null;
    if (isset($imageAttrs['cache_dir'])) {
        $cacheDir = rtrim($imageAttrs['cache_dir'], '/') . '/';
        unset($imageAttrs['cache_dir']);
    } else {
        $cacheDir = get_option('OCTOPUS_CACHE_DIR');
        if (!$cacheDir) {
            throw new SmartyException("No cache dir available for resizing.");
        }
        $cacheDir .= 'resize/';
    }

    $cacheName = "{$mtime}_r_{$hash}_{$width}x{$height}";
    $cacheFile = $cacheDir . $cacheName;

    if (is_file($cacheFile)) {
        return $cacheFile;
    }

    // Not in cache, so do some resizing.
    $i = new Octopus_Image(array(array(
        'action' => 'r',
        'width' => $width,
        'height' => $height,
        'mod' => ''
    )));

    $i->keep_type = true;

    $i->processImages($cacheDir, $cacheName, $file);

    return $cacheFile . '.' . $info['extension'];
}

?>

