<?php

/**
 * Looks for a file.
 *
 * @param $paths Mixed Path to the file, relative to the directories being
 * searched. Can also be an array.
 *
 * @param $dirs Array Directories to search. Defaults to SITE_DIR and
 * OCTOPUS_DIR.
 *
 * @param $options Array Any additional options.
 *
 * @return Mixed The full path to a file, or false if the file isn't found.
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
function get_file($paths, $dirs = null, $options = null) {

    $options = $options ? $options : array();

    if (!isset($options['newest'])) $options['newest'] = false;
    if (!isset($options['extensions'])) $options['extensions'] = false;
    if (!isset($options['debug'])) $options['debug'] = false;

    if (defined('DEV')) {
        if (!DEV) $options['debug'] = false;
    } else if (!isset($options['debug'])) {
        $options['debug'] = false;
    }

    if ($dirs === null) {

        $dirs = array();

        if (!empty($options['OCTOPUS_DIR'])) {
            $dirs[] = $options['OCTOPUS_DIR'];
        } else if (defined('OCTOPUS_DIR')) {
            $dirs[] = OCTOPUS_DIR;
        }

        if (!empty($options['SITE_DIR'])) {
            $dirs[] = $options['SITE_DIR'];
        } else if (defined('SITE_DIR')) {
            $dirs[] = SITE_DIR;
        }

    }

    // allow dirs to be single string
    if (is_string($dirs)) {
        $dirs = array($dirs);
    }

    if (!is_array($paths)) {
        $paths = array($paths);
    }

    foreach($paths as $path) {

        $path = ltrim($path, '/');
        $newestTime = 0;
        $newestPath = false;
        $found = false;

        foreach($dirs as &$dir) {

            $dir = rtrim($dir, '/') . '/';
            $fullPath = $dir . $path;

            if (empty($options['extensions'])) {

                if ($options['debug']) {
                        echo '<pre>' . htmlspecialchars($fullPath) . '</pre>';
                    }

                $found = _get_file_helper($fullPath, $options, $newestTime, $newestPath);
                if ($found && !$options['newest']) {
                    return $found;
                }

            } else {

                foreach($options['extensions'] as $ext) {

                    if ($options['debug']) {
                        echo '<pre>' . htmlspecialchars($fullPath . $ext) . '</pre>';
                    }

                    $found = _get_file_helper($fullPath . $ext, $options, $newestTime, $newestPath);
                    if ($found && !$options['newest']) {
                        return $found;
                    }
                }

            }
        }

    }

    // TODO: Search modules for file

    if ($options['newest']) {
        return $newestPath;
    } else {
        return $found;
    }

}

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
function _get_file_helper($path, $options, &$newestTime, &$newestPath) {

    if (!file_exists($path)) {
        return false;
    }

    if ($options['newest']) {

        $time = filemtime($path);
        if ($time > $newestTime) {
            $newestTime = $time;
            $newestPath = $path;
        }

    }

    return $path;
}

/**
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
function newest_file($file1, $file2 = null) {

    if ($file2 === null && is_array($file1)) {
        list($file1, $file2) = $file1;
    }

    $m1 = filemtime($file1);
    $m2 = filemtime($file2);

    if ($m1 >= $m2) {
        return $file1;
    } else {
        return $file2;
    }

}

function get_site_file_url($file, $options = array()) {

    $siteDir = isset($options['SITE_DIR']) ? $options['SITE_DIR'] : SITE_DIR;
    $siteDir = rtrim($siteDir, '/') . '/';
    $file = ltrim($file, '/');

    $actualFile = realpath($siteDir . $file);
    $actualDir = dirname($actualFile) . '/';

    if (!starts_with($actualDir, $siteDir)) {
        return false;
    }

    return u('/site/' . $file, $options);
}

/**
 * Creates all directories necessary, and then touches the given file. Also,
 * the function name sounds slightly dirty.
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
function recursive_touch($file) {

    $dirs = explode('/', $file);
    $file = array_pop($dirs);
    $dir = implode('/', $dirs);

    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    chmod($dir, 0777);
    return touch($dir . '/' . $file);

}

/**
 * A glob that always returns an array.
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
function safe_glob($args) {
    $ret = glob($args);
    if (!is_array($ret)) {
        $ret = array();
    }

    return $ret;
}

/**
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
function get_filename($file, $short = false) {

    $parts = explode('/', $file);
    $l = count($parts);
    $filename = $parts[$l - 1];

    if ($short) {
        $dot = strrpos($filename, '.');
        if ($dot !== false && $dot !== 0) {
            $filename = substr($filename, 0, $dot);
        }
    }

    return $filename;

}

/**
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
function get_extension($file) {
    $filename = get_filename($file);
    $dot = strrpos($filename, '.');

    if ($dot === false || $dot === 0) {
        return '';
    }

    $ext = substr($filename, $dot);
    return $ext;
}

/**
 * @deprecated Use
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
function mime_to_extension($mime) {
    switch($mime) {
        case 'image/jpeg':
            return '.jpg';
        case 'image/gif':
            return '.gif';
        case 'image/png':
            return '.png';
    }

    return false;
}

/**
 * Creates a directory, but only if it is underneath $baseDir.
 * @param String $dir Directory to create
 * @param String $baseDir Directory that $dir must be inside. If not specified, ROOT_DIR is used.
 * @param Bool $throwExceptions If directory creation fails, whether or not to throw an exception
 * or just return false.
 * @return bool True if directory now exists, false otherwise.
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
function mkdir_safe($dir, $baseDir = null, $recursive = true, $mode = 0777, $throwExceptions = true) {

    if (!$baseDir) {
        $baseDir = get_option('ROOT_DIR');
        if (!$baseDir) {
            if ($throwExceptions) {
                throw new Octopus_Exception("mkdir_safe: No basedir specified.");
            } else {
                return false;
            }
        }
    }

    $dir = realpath($dir);
    $baseDir = realpath($baseDir);

    $dir = rtrim($dir, '/');
    $baseDir = rtrim($baseDir, '/');

    if (strcmp($dir, $baseDir) === 0) {
        return true;
    }

    if (!preg_match('#^' . preg_quote($baseDir) . '/#', $dir)) {

        if ($throwExceptions) {
            throw new Octopus_Exception("Cannot create directory outside of basedir.");
        } else {
            return false;
        }

    }

    if (is_dir($dir)) {
        return true;
    }

    $failed = null;

    if ($recursive) {

        if (!is_dir($baseDir)) {

            if ($throwExceptions) {
                throw new Octopus_Exception("Basedir does not exist: $baseDir");
            } else {
                return false;
            }

            $toCreate = explode('/', substr($dir, strlen($baseDir)));
            $path = $baseDir;
            foreach($toCreate as $d) {
                if (!$d) continue;
                $path .= "/$d";
                if (!is_dir($path)) {
                    if (!@mkdir($path, $mode)) {
                        $failed = $path;
                        break;
                    }
                }
            }

        }

    } else {

        if (@mkdir($dir, $mode)) {
            return false;
        } else {
            $failed = $dir;
        }

    }

    if ($failed) {

        if ($throwExceptions) {
            throw new Octopus_Exception("Directory creation failed: $failed");
        } else {
            return false;
        }

    }

    return true;
}

/**
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
function file_count($dir) {

    $count = 0;
    foreach (glob(end_in('/', $dir) . '*') as $file) {
        if (is_dir($file)) {
            $count += file_count($file);
        } else {
            $count++;
        }
    }

    return $count;

}

/**
 * Deletes somethin'
 * @param $force bool Whether to carry on if a delete fails.
 * @return bool True on success, false otherwise.
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
function recursive_delete($f, $force = false, &$failures = null) {

    $f = rtrim($f, '/');

    if (!file_exists($f)) {
        return true;
    }

    $success = true;

    if (is_link($f)) {

        if (@unlink($f)) {
            return true;
        } else {
            if ($failures !== null) $failures[] = $f;
            return false;
        }

    } else if (is_dir($f)) {

        $handle = opendir($f);
        if ($handle === false) {
            return false;
        }

        while (($file = readdir($handle)) !== false) {

            if ($file === '.' || $file === '..') {
                continue;
            }

            $file = $f . '/' . $file;

            if (!recursive_delete($file, $force, $failures)) {
                $success = false;
                if (!$force) return $success;
            }

        }

        closedir($handle);

        if (@rmdir($f)) {
            return $success;
        } else {
            if ($failures) $failures[] = $f;
            return false;
        }

    } else {

        if (@unlink($f)) {
            return true;
        } else {
            if ($failures !== null) $failures[] = $f;
            return false;
        }
    }
}


