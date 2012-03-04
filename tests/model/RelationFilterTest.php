<?php

/**
 * @group Model
 */
class ModelRelationFilterTest extends Octopus_DB_TestCase
{
    function __construct()
    {
        parent::__construct('model/relation-filter-data.xml');
    }

    function testAccessComments() {
        $car = new Car(1);
        $this->assertEquals(2, count($car->comments));
        $car = new Car(2);
        $this->assertEquals(1, count($car->comments));

        $boat = new Boat(1);
        $this->assertEquals(1, count($boat->comments));
        $boat = new Boat(2);
        $this->assertEquals(2, count($boat->comments));
    }

    function testCommentParent() {
        $comment = new Comment(6);
        $boat = $comment->parent;

        $this->assertTrue($boat instanceof Boat);
        $this->assertEquals(2, $boat->boat_id);
    }

    function testAddComment() {

        $comment = new Comment();
        $comment->content = 'Added Comment';

        $boat = new Boat();
        $boat->name = 'Added Boat';
        $this->assertTrue(!!$boat->save(), 'boat save succeeds');

        $boat->addComment($comment);

        $boat = new Boat(3);
        $this->assertEquals('Added Boat', $boat->name);

        $comments = $boat->comments;
        $this->assertEquals(1, count($comments));
        $this->assertEquals('Added Comment', $comments->first()->content);

    }

    function testAddUserComment() {

        $user = new CommentUser();
        $user->name = 'Teddy';
        $this->assertTrue(!!$user->save(), 'user save succeeds');

        $comment = new Comment();
        $comment->content = 'testAddUserComment';
        $comment->creator = $user;
        $this->assertTrue($comment->creator === $user, 'creator set');

        $boat = new Boat(2);
        $boat->addComment($comment);

        $c = new Comment(7);
        $this->assertEquals('testAddUserComment', $c->content);
        $this->assertEquals(2, $comment->creator->id);

    }

    function xtestAddUserCommentLazy() {

        $user = new CommentUser();
        $user->name = 'Teddy';
        $user->save();

        $user = new CommentUser(2);

        $comment = new Comment();
        $comment->content = 'testAddUserCommentLazy';
        $comment->creator = $user;

        $boat = new Boat(2);
        $boat->addComment($comment);

        $c = new Comment(7);
        $this->assertEquals('testAddUserCommentLazy', $c->content);
        $this->assertEquals(2, $comment->creator->id);

    }


}
