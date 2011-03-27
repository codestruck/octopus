<?php


// fetching
$post = new Post();
$somePosts = $post->all();

$somePosts = $post->find('title', 'Blog Post Title');
// or
$somePosts = $post->find(array('title' => 'Blog post title'));

$aPost = $post = new Post($id); // ?

// find returns array, while ID in constructor returns item ?



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
$somePosts = $post->find('author_id', 2);


// Author

$author = new Author();
$somePosts = $author->posts->all();
$somePosts = $author->posts->find('title', 'Third blog post');

?>
