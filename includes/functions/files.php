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
     */
    function recursive_touch($file) {

        $dirs = explode('/', $file);
        $file = array_pop($dirs);
        $dir = implode('/', $dirs);

        @mkdir($dir, 0777, true);
        return touch($dir . '/' . $file);

    }

    /**
     * A glob that always returns an array.
     */
    function safe_glob($args) {
        $ret = glob($args);
        if (!is_array($ret)) {
            $ret = array();
        }

        return $ret;
    }

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

    function get_extension($file) {
        $filename = get_filename($file);
        $dot = strrpos($filename, '.');

        if ($dot === false || $dot === 0) {
            return '';
        }

        $ext = substr($filename, $dot);
        return $ext;
    }


?>
