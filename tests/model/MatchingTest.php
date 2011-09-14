<?php

/**
 * @group Model
 */
class MatchingTest extends Octopus_DB_TestCase {

    function __construct() {
        parent::__construct('model/find-data.xml');
    }

    function testMatchingHasOneSql() {

        $this->markTestSkipped();

        $matt = FindPost::All()->matching('Hinz');
        $this->assertEquals('SELECT * FROM find_posts INNER JOIN find_authors on (`find_posts`.`author_id` = `find_authors`.`find_authors_id`) WHERE (`find_posts`.`title` LIKE ? OR `find_authors`.`name` LIKE ?)', $matt->getSql());

    }

    function testMatchingHasOneCount() {

        $this->markTestSkipped();

        $matt = FindPost::All()->matching('Hinz');
        $this->assertEquals(3, count($matt));

    }


}
