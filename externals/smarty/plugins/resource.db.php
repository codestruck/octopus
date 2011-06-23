<?php

SG::loadModel('Page', 'pages');
require_once(PUBLIC_FUNCTION_DIR . 'fncCleanup.php');

/*
 * Smarty Plugin
 */
function smarty_resource_db_source ($tpl_name, &$tpl_source, $smarty_obj) {

    $pageModel =& SG_Model_Page::singleton();

    if ($pageModel->loadTemplate($tpl_name)) {

        $contents = $pageModel->contents;

        if ($pageModel->db_template) {
            $contents = replace_emails($contents);
        }

        $tpl_source = $contents;

        return true;
    }

    return false;

}


function smarty_resource_db_timestamp($tpl_name, &$tpl_timestamp, $smarty_obj) {

    $pageModel =& SG_Model_Page::singleton();

    if ($pageModel->loadTemplate($tpl_name)) {
        $tpl_timestamp = $pageModel->timestamp;
        return true;
    }

    return false;

}

function smarty_resource_db_secure($tpl_name, &$smarty)
{
    // assume all templates are secure
    return true;
}

function smarty_resource_db_trusted($tpl_name, &$smarty)
{
    // not used for templates
}

?>
