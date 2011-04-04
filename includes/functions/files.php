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

        if ($dirs === null) {

            $octopusDir = OCTOPUS_DIR;
            $siteDir = SITE_DIR;

            if ($options) {

                if (!empty($options['OCTOPUS_DIR'])) $rootDir = $options['OCTOPUS_DIR'];
                if (!empty($options['SITE_DIR'])) $siteDir = $options['SITE_DIR'];

            }

            $dirs = array($siteDir, $octopusDir);

        }

        $options = ($options === null ? array() : $options);
        if (!isset($options['newest'])) $options['newest'] = false;
        if (!isset($options['extensions'])) $options['extensions'] = false;
        if (!isset($options['debug'])) $options['debug'] = false;

        if (!DEV) $options['debug'] = false;

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

    /**
     * @param $path String Path to the file, relative to the theme dir.
     * @param $options String Extra options.
     * @return Mixed The path to a file for the current theme, or false if
     * the file could not be found.
     */
    function get_theme_file($path, $options = null) {

        //TODO: need global settings var w/ theme info

        $path = ltrim($path, '/');

        $result = get_file("site/themes/default/$path");
        if ($result) return $result;

        return get_file("themes/default/$path");
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

?>
