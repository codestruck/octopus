<?php
/**
 * This is a version of index.php meant to be used in deployed Octopus apps.
 * It assumes that it is located in ROOT_DIR, rather than in OCTOPUS_DIR.
 * Other than that, it is identical to index.php
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */

    require_once('octopus/includes/core.php');

    bootstrap();
    render_page();