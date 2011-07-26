<?php

function smarty_function_shortcode($params, &$smarty)
{
    if (!isset($params['name'])) {
        trigger_error('Must pass name argument to shortcode function');
        return;
    }

    $fnc = 'shortcode_' . $params['name'];
    unset($params['name']);
    return $fnc($params);
}

?>
