<?php

if (!is_dir(OCTOPUS_PRIVATE_DIR . 'htmlpurifier')) {
    mkdir(OCTOPUS_PRIVATE_DIR . 'htmlpurifier', 0777);
}

require_once($EXTERNAL_DIR . 'htmlpurifier-4.3.0/library/HTMLPurifier.auto.php');
