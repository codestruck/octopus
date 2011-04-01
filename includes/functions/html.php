<?php

    /**
     * Generates a link tag.
     */
    function a($href, $text) {
        $href = htmlspecialchars(u($href));
        return "<a href=\"$href\">$text</a>";
    }

    /**
     * @return string URL to a CDN-hosted jQuery
     */
    function get_jquery_url($version = '1.5', $secure = null) {

        if ($secure === null) {
            $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on';
        }

        return ($secure ? 'https' : 'http') . '://ajax.googleapis.com/ajax/libs/jquery/' . $version . '/jquery.min.js';
    }

?>
