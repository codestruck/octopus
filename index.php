<?php

    require_once('includes/core.php');

    bootstrap();

    $file = '404.php';

    $URI = isset($_GET['__path']) ? $_GET['__path'] : '/';
    $item = $NAV->find($URI);

    if ($item) {
        $file = $item->getFile();
    }

    include($file);

?>

