<?php

/**
 * @group Model
 */
class MatchingTest extends Octopus_DB_TestCase {

    function __construct() {
        parent::__construct('model/find-data.xml');
    }

    function testMatchingHasOneSql() {

        $matt = FindPost::all()->matching('Hinz');
        $this->assertEquals('SELECT * FROM find_posts WHERE (`find_posts`.`title` LIKE ?) OR (`find_posts`.`author_id` IN (SELECT `find_author_id` FROM `find_authors` WHERE `find_authors`.`name` LIKE ?))', $matt->getSql());

    }

    function testMatchingHasOneCount() {

        $matt = FindPost::all()->matching('Hinz');
        $this->assertEquals(3, count($matt));

    }

    function testTest() {

        $matt = FindPost::all()->where('author.name', 'Matt Hinz');
        $this->assertEquals('SELECT * FROM find_posts WHERE `find_posts`.`author_id` IN (SELECT `find_author_id` FROM `find_authors` WHERE `find_authors`.`name` = ?)', $matt->getSql());
        $this->assertEquals(3, count($matt));

    }

}
