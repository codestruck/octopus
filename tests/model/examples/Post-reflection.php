<?php

// http://www.php.net/manual/en/class.reflectionclass.php

class SG_Model { /* dummy */ }

/**
 * !BelongsTo author
 */
class Post extends SG_Model {
    public $post_id;
    public $title;
    public $body;

    // has an author
}

$post = new Post();
$r = new ReflectionClass($post);
$comment = $r->getDocComment();
var_dump($comment);

?>
