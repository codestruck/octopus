<?php

    $post = new Post();
    $post->title = 'My post';
    $post->body = '<b>This is the text of my post</b>';
    $post->author = 42; // == new Author(42)

    if ($post->validate()) {
        // post is valid!!!
    }

    // save() implicitly calls validate(), returns post_id on success, false on failure
    if ($post->save()) {
        echo $post->slug; // "my-post"
    } else {

        echo '<ul class="errors">';
        foreach($post->getErrors() as $err) {
            echo "<li>{$err['field']} - {$err['message']}</li>";
        }
        echo '</ul>';

    }

    if ($errors = $post->getErrors()) { // implicitly calls validate(), returns false if no errors
    }

    $somePosts = $post->all();
    $livePosts = Post::findByActive(1);
    Post::all()->active();

    // 'get' = returns at most 1 post
    $post = Post::get(1);
    $post = Post::getBySlug('my-post'); // use magic methods to generate getBy* methods

    // 'find' = returns sortable/pageable result set
    $posts = Post::find(array('title' => 'My Title'));

    $posts = Post::find(array('title LIKE' => '*keyword*'));
    $posts = Post::find(array('title LIKE' => '*keyword', 'OR', 'created <' => '2008-01-01'));

    $posts = Post::findByAuthor(42)->orderBy('created');

    foreach($posts as $post) { // no SELECT executed until here

    }

    // modify where clause after initial find call
    $posts = Post::findByAuthor(42)->where(array('title LIKE' => '*keyword*'));

    // some aggregate functions
    $totalLikes = Post::findByAuthor(42)->sum('facebook_likes');
    $total = Post::findByAuthor(42)->count();

    $post = Post::get(1);
    $post = Post::get(array('title LIKE' => 'Show'))->orderBy('display_order');

    $post = Post::find(array('post_id' => 1));
    $post->delete();

    $post = new Post(1); // = stub post, no SELECT queries executed
    $post->delete();

?>
