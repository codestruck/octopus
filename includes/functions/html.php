<?php

    /**
     * Generates a link tag.
     */
    function a($href, $text) {
        $href = htmlspecialchars(u($href));
        return "<a href=\"$href\">$text</a>";
    }

?>
