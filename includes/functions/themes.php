<?php

    /**
     * @return string The name of the current theme.
     */
    function current_theme() {

        $app = Octopus_App::singleton();
        return $app->getTheme();
    }

    /**
     *
     */
    function get_css_file($file) {
        return get_theme_file('css/' . $file, true);
    }

    /**
     * @return Mixed The full absolute path to a file in the current theme, or
     * false if no matching file is found.
     *
     * Options:
     *
     *  <table>
     *  <tr>
     *      <th>Option</th>
     *      <th>Default</th>
     *      <th>Meaning</th>
     *  </tr>
     *  <tr>
     *      <td>
     *          use_src
     *      </td>
     *      <td>
     *          false
     *      </td>
     *      <td>
     *          If true, and a file named <b>file_src.ext</b> is present and
     *          newer than the matched file, it will be returned instead.
     *      </td>
     *  </tr>
     *
     */
    function get_theme_file($file, $options = array()) {

        // Allow get_theme_file($file, true) to use _src
        if (is_bool($options)) {
            $options = array('use_src' => $options);
        }

        extract($options);
        $file = trim($file, '/');
        $use_src = isset($use_src) ? $use_src : false;
        $SITE_DIR = rtrim(isset($SITE_DIR) ? $SITE_DIR : SITE_DIR, '/') . '/';

        if (empty($theme)) $theme = current_theme($options);
        if ($theme) $theme = rtrim($theme, '/') . '/';

        $ogPath = $SITE_DIR . 'themes/' . $theme . $file;

        if (!$use_src) {
            return file_exists($ogPath) ? $ogPath : false;
        }

        $src_marker = isset($src_marker) ? $src_marker : '_src';

        $parts = explode('/', $file);
        $name = array_pop($parts);

        $dotPos = strrpos($name, '.');
        if ($dotPos === false) {
            $ext = '';
        } else {
            $ext = substr($name, $dotPos);
            $name = substr($name, 0, $dotPos);
        }
        $name = count($parts) == 0 ? $name : implode('/', $parts) . '/' . $name;


        $srcPath = $SITE_DIR . 'themes/' . $theme . $name . $src_marker . $ext;

        $ogExists = file_exists($ogPath);
        $srcExists = file_exists($srcPath);

        if ($ogExists && $srcExists) {

            $ogTime = filemtime($ogPath);
            $srcTime = filemtime($srcPath);

            if ($ogTime > $srcTime) {
                return $ogPath;
            } else {
                return $srcPath;
            }

        } else if ($ogExists) {
            return $ogPath;
        } else if ($srcExists) {
            return $srcPath;
        } else {
            return false;
        }
    }

    /**
     * @return Mixed A path to a file in the current theme suitable for sending
     * to the browser.
     */
    function get_theme_file_url($file, $options = array()) {

        $file = get_theme_file($file, $options);
        if (!$file) return $file;

        $siteDir = rtrim(isset($options['SITE_DIR']) ? $options['SITE_DIR'] : SITE_DIR, '/') . '/';
        $siteDirLen = strlen($siteDir);

        if (strncmp($file, $siteDir, $siteDirLen) == 0) {
            $file = substr($file, $siteDirLen);
        }

        $urlBase = isset($options['URL_BASE']) ? $options['URL_BASE'] : URL_BASE;

        return $urlBase . 'site/' . $file;
    }

?>
