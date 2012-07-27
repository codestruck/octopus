<?php

    /**
     * @return string The name of the current theme.
     */
    function get_theme() {
    	$resp = Octopus_Response::current();
        return $resp ? $resp->getTheme() : '';
    }

    /**
     * Sets the current theme.
     * @param String $theme The name of the theme to use. Should be a directory
     * in the site/themes directory.
     */
    function set_theme($theme) {
    	$resp = Octopus_Response::current();
        $resp->setTheme($theme);
    }

    /**
     * @return Mixed The full absolute path to a file in the current theme, or
     * false if no matching file is found.
     */
    function get_theme_file($file, $options = array()) {

        $file = ltrim($file, '/');
        $theme = isset($options['theme']) ? $options['theme'] : get_theme();

        foreach(array('SITE_DIR', 'OCTOPUS_DIR') as $dir) {

            $dir = isset($options[$dir]) ? end_in('/', $options[$dir]) : get_option($dir);
            if (!$dir) continue;

            $dir = $dir . 'themes/' . $theme . '/';

            $f = $dir . $file;

            if (is_file($f)) {
                return $f;
            }

        }

        return false;

    }

