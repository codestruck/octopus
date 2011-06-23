<?php

function smarty_function_shortcode($params, &$smarty)
{
    $fnc = 'shortcode_' . $params['name'];
    unset($params['name']);
    return $fnc($params);
}

?>
