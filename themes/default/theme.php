<?php

    if (defined('URL_BASE')) {
        $p = Octopus_Html_Page::singleton();
        $p->addCss(URL_BASE . 'octopus/themes/default/styles.css');
    }

?>