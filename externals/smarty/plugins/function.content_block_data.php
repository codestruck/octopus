<?php

function smarty_function_content_block_data($args, &$smarty)
{

    $s = new SG_DB_Select();
    $s->table('content_blocks');
    $s->where('name = ?', $args['name']);

    $data = array();
    $query = $s->query();

    while ($result = $query->fetchRow()) {
        $data[$result['type']] = unserialize($result['values']);
    }

    $smarty->assign($args['assign'], $data);

}

?>
