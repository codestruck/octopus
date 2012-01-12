<?php

    /**
     * @return string The name of the current theme.
     */
    function current_theme() {

        $app = Octopus_App::singleton();
        return $app->getTheme();
    }

    /**
     * @return Mixed The full absolute path to a file in the current theme, or
     * false if no matching file is found.
     */
    function get_theme_file($file, $options = array()) {

        $file = ltrim($file, '/');
        $theme = isset($options['theme']) ? $options['theme'] : current_theme();

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

?>
