<?php


// fetching
$post = new Post();
$post->all();

$post->find('title', 'Blog Post Title');
// or
$post->find(array('title' => 'Blog post title'));

// creating
$post = new Post();
$post->title = 'Post number 2';
$post->body  = 'Body contents';
$post->save();
$id = $post->post_id;

// deleting
$post = new Post();
$post->find('post_id', 1);
$post->delete();

// find by author
$post = new Post();
$post->find('author_id', 2);


// Author

$author = new Author();
$author->posts->all();
$author->posts->find('title', 'Third blog post');

?>
