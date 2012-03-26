<?php
/**
 * This is a version of index.php meant to be used in deployed Octopus apps.
 * It assumes that it is located in ROOT_DIR, rather than in OCTOPUS_DIR.
 * Other than that, it is identical to index.php
 * @see index.php
 */

    require_once('octopus/includes/core.php');

    bootstrap();
    render_page();