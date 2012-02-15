<?php

/**
 * @group Model
 */
class ResultSetOperationsTest extends Octopus_DB_TestCase
{

    function __construct()
    {
        parent::__construct('model/find-data.xml');
    }

    function testMapSingle() {

        $result = array(
            'Matt Hinz',
            'Mike Estes',
        );

        $this->assertEquals($result, FindAuthor::all()->map('name'));

    }

    function testMapKeyed() {

        $result = array(
           'slug-for-post-1' => 'Title for Post 1',
           'slug-for-post-2' => 'Title for Post 2',
           'post-3-case-test-foo' => 'Post 3 - Case Test - FOO',
           'post-4-case-test-foo' => 'Post 4 - Case Test - foo',
           'magic-method-test-post' => 'Magic Method Test Post',
           'second-magic-method-test-post' => 'Second Magic Method Test Post',
        );

        $this->assertEquals($result, FindPost::all()->map('slug', 'title'));

    }

    function testMapArray() {

        $result = array(
           array(1, 'slug-for-post-1', 'Title for Post 1'),
           array(2, 'slug-for-post-2', 'Title for Post 2'),
           array(3, 'post-3-case-test-foo', 'Post 3 - Case Test - FOO'),
           array(4, 'post-4-case-test-foo', 'Post 4 - Case Test - foo'),
           array(5, 'magic-method-test-post', 'Magic Method Test Post'),
           array(6, 'second-magic-method-test-post', 'Second Magic Method Test Post'),
        );

        $this->assertEquals($result, FindPost::all()->map(array('find_post_id', 'slug', 'title')));

    }

}
