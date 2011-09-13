<?php

/**
 * {url path="/view/all"}
 *
 * Options:
 *  - default (array) query arguments
 *  - sep (string) custom seperator, defaults to &amp;
 *  - remove_arg (string) argument to remove from query
 *  - ending (bool) force ending in ? or & to allow easy appending
 *  - Anything Else (string) additional args to insert / replace in query
 */
function smarty_function_url($params, $template) {


    $default = isset($params['default']) ? $params['default'] : array();
    $path = $params['path'];
    $sep = isset($params['sep']) ? $params['sep'] : '&amp;';
    $forceEnding = isset($params['ending']) ? $params['ending'] : false;

    if (isset($params['remove_arg'])) {
        $default[ $params['remove_arg'] ] = null;
    }

    unset($params['ending']);
    unset($params['remove_arg']);
    unset($params['default']);
    unset($params['path']);
    unset($params['sep']);

    $args = array_merge($default, $params);

    $url = $path;

    if (count($args)) {
        $url .= '?' . octopus_http_build_query($args, $sep);
    }

    if ($forceEnding) {
        if (count($args)) {
            $url = end_in($sep, $url);
        } else {
            $url = end_in('?', $url);
        }
    }

    return u($url);

}
