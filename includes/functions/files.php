<?php

    /**
     * Checks the sitedir for a file, returning its path. If the file is not
     * found in the sitedir, returns a path to the file in core.
     * @return string The full path to a file.
     */
    function get_file($path, $options = null) {

        $rootDir = ROOT_DIR;
        $siteDir = SITE_DIR;

        if ($options) {

            if (!empty($options['ROOT_DIR'])) $rootDir = rtrim($options['ROOT_DIR'], '/') . '/';
            if (!empty($options['SITE_DIR'])) $siteDir = rtrim($options['SITE_DIR'], '/') . '/';

        }

        $path = ltrim($path, '/');

        if (file_exists($siteDir . $path)) {
            return $siteDir . $path;
        }

        if (file_exists($rootDir . $path)) {
            return $rootDir . $path;
        }

        return false;
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
