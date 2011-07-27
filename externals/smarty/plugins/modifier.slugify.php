<?php
/**
 * Converts arbitrary string to a slug.
 *
 */
function smarty_modifier_slugify($string)
{
    $string = strtolower(trim($string));
    $string = preg_replace('/[^a-z0-9-]+/', '-', $string);
    return trim($string, '-');
}

?>
