<?php

    Octopus::loadExternal('pear');

    require_once(dirname(__FILE__) . '/Pager_Wrapper.php');

/**
 * Pager_Wrapper adapted to work with Octopus result sets.
 */
function Pager_Wrapper_ResultSet($resultSet, $pager_options = array(), $disabled = false, $fetchMode = null)
{
   if (!array_key_exists('totalItems', $pager_options)) {
        $pager_options['totalItems'] = $resultSet->count();
    }
    require_once 'Pager/Pager.php';
    $pager = @Pager::factory($pager_options);

    $page = array();
    $page['totalItems'] = $pager_options['totalItems'];
    $page['links'] = $pager->links;
    $page['page_numbers'] = array(
        'current' => $pager->getCurrentPageID(),
        'total'   => $pager->numPages()
    );
    list($page['from'], $page['to']) = $pager->getOffsetByPageId();

    $resultSet = ($disabled)
        ? $resultSet->unlimit()
        : $resultSet->limit($page['from']-1, $pager_options['perPage']);

    $page['data'] = $resultSet;

    if ($disabled) {
        $page['links'] = '';
        $page['page_numbers'] = array(
            'current' => 1,
            'total'   => 1
        );
    }
    return $page;
}

/**
 * Pager_Wrapper adapted to work with arrays
 */
function Pager_Wrapper_Array(&$array, $pager_options = array(), $disabled = false, $fetchMode = null)
{
   if (!array_key_exists('totalItems', $pager_options)) {
        $pager_options['totalItems'] = count($array);
    }
    require_once 'Pager/Pager.php';
    $pager = @Pager::factory($pager_options);

    $page = array();
    $page['totalItems'] = $pager_options['totalItems'];
    $page['links'] = $pager->links;
    $page['page_numbers'] = array(
        'current' => $pager->getCurrentPageID(),
        'total'   => $pager->numPages()
    );
    list($page['from'], $page['to']) = $pager->getOffsetByPageId();

    if ($disabled) {
        $page['data'] = $array;
    } else {
        $page['data'] = array_slice($array, $page['from'] - 1, $pager_options['perPage']);
    }

    if ($disabled) {
        $page['links'] = '';
        $page['page_numbers'] = array(
            'current' => 1,
            'total'   => 1
        );
    }
    return $page;
}


?>
