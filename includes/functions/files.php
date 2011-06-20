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
     * Determines the actual, real name of $file. Emulates a case-insensitive
     * file system.
     * @param $file String Filename to examine
     * @param $force bool Whether to allow shortcutting out if the file exists
     * @return Mixed The actual full filename (correct case) if found,
     * otherwise false.
     */
    function get_true_filename($file, $force = false) {

        if (!$force && is_file($file)) {
            return $file;
        }

        $parts = explode('/', $file);
        $result = '';

        if (_get_true_filename_worker($parts, 0, $result)) {
            return $result;
        }

        return false;
    }

    function _get_true_filename_worker(&$pathParts, $index, &$filename) {

        if ($index >= count($pathParts)) {
            return true;
        }

        $fullName = $pathParts[$index];

        if (!$fullName) {
            return _get_true_filename_worker($pathParts, $index + 1, $filename);
        }

        $dotPos = strrpos($fullName, '.');

        if ($dotPos) {
            $ext = substr($fullName, $dotPos);
            $name = substr($fullName, 0, $dotPos);
        } else {
            $ext = '';
            $name = $fullName;
        }

        $pattern = _globify($name, 3) . _globify($ext);

        foreach(glob($filename . '/' . $pattern) as $candidate) {

            $candidateName = basename($candidate);

            if (strcasecmp($fullName, $candidateName) == 0) {
                $filename .= '/' . $candidateName;
                return _get_true_filename_worker($pathParts, $index+1, $filename);
            }
        }

        return false;
    }

    function _globify($str, $maxLen = 0) {

        $len = strlen($str);
        $result = '';

        for($i = 0; (!$maxLen) || $i < $maxLen; $i++) {

            if ($i >= $len) {
                break;
            }

            $uc = strtoupper(substr($str, $i, 1));
            $lc = strtolower($uc);

            if ($lc == '[') {
                $result .= '[\\' . $lc . ']';
            } else if ($lc == $uc) {
                $result .= $lc;
            } else {
                $result .= '[' . $uc . $lc . ']';
            }
        }

        if ($maxLen && $maxLen < $len) {
            $result .= '*';
        }


        return $result;

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

?>
