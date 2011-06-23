<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */

SG::loadModel('Product_Category', 'products');

require_once(PUBLIC_FUNCTION_DIR . 'renderMenu.php');

/**
 * Smarty {product_nav} function plugin
 */
function smarty_function_product_nav($params, &$smarty)
{
    $model = new SG_Model_Product_Category();
    $model->cat_path = array();
    $model->lang = 'en';

    $max = 0;
    if ($params['max']) {
        $max = $params['max'];
    }

    $nav = $model->getSubnav($params['cat_id'], $params['base_pbn'], 1, $max);

    $output = StandardRenderMenu($nav, 1, 'smarty');

    return $output;

}

/* vim: set expandtab: */

?>
