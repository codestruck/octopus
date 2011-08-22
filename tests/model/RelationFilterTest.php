<?php

Octopus::loadClass('Octopus_DB');
Octopus::loadClass('Octopus_Model');

db_error_reporting(DB_PRINT_ERRORS);

class Comment extends Octopus_Model {
    protected $fields = array(
        'content',
        'item_type',
        'item_id' => array(
            'type' => 'numeric',
        ),
        'parent' => array(
            'type' => 'hasOne',
            'filter' => true
        ),
    );
}

class Car extends Octopus_Model {
    protected $fields = array(
        'name',
        'comment' => array(
            'type' => 'hasMany',
            'filter' => true,
        ),
    );
}

class Boat extends Octopus_Model {
    protected $fields = array(
        'name',
        'comment' => array(
            'type' => 'hasMany',
            'filter' => true,
        ),
    );
}

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
        $boat->save();

        $boat->addComment($comment);

        $boat = new Boat(3);
        $this->assertEquals('Added Boat', $boat->name);

        $comments = $boat->comments;
        $this->assertEquals(1, count($comments));
        $this->assertEquals('Added Comment', $comments->first()->content);

    }


}
