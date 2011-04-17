<?php

    // TODO: How do we want to handle migrations?

    function migrate_core($db, $schema) {

        $settings = $schema->newTable('settings');
        $settings->newTextSmall('name', 100);
        $settings->newTextLarge('value');
        $settings->create();



    }



?>
