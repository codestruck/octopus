<?php

/*
 * Smarty Plugin
 */
function smarty_resource_nav_source ($tpl_name, &$tpl_source, $smarty_obj) {

    $file = SITE_DIR . 'w/nav/' . $tpl_name;

    if (is_file($file)) {
        $tpl_source = file_get_contents($file);
        return true;
    }

    return false;

}


function smarty_resource_nav_timestamp($tpl_name, &$tpl_timestamp, $smarty_obj) {

    $file = SITE_DIR . 'w/nav/' . $tpl_name;

    if (is_file($file)) {
        $tpl_timestamp = filemtime($file);
        return true;
    }

    return false;

}

function smarty_resource_nav_secure($tpl_name, &$smarty)
{
    // assume all templates are secure
    return true;
}

function smarty_resource_nav_trusted($tpl_name, &$smarty)
{
    // not used for templates
}

?>
