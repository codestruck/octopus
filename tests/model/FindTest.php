<?php

require_once(dirname(dirname(__FILE__)) . '/SG_DB_TestCase.php');

class FindAuthor extends SG_Model {

    static $fields = array(
        'name' => array('type' => 'string')
    );

}

class FindPost extends SG_Model {

    static $fields = array(
        'title' => array(
            'type' => 'string'
        ),
        'slug' => array(
            'type' => 'slug'
        ),
        'body' => array(
            'type' => 'html'
        ),
        'author' => array(
            'class' => 'FindAuthor',
            'type' => 'has_one'
        ),
        'active' => array(
            'type' => 'toggle',
        ),
        'display_order' => array(
            'type' => 'order',
        ),
        'created',
        'updated',

    );

}

/**
 * @group find
 */
class FindTest extends SG_DB_TestCase {

    function __construct() {
        parent::__construct('model/find-data.xml');
    }

    function createTables(&$db) {

        $sql = "CREATE TABLE findposts (
                `findpost_id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `title` varchar ( 255 ) NOT NULL,
                `slug` varchar ( 255 ) NOT NULL,
                `body` text NOT NULL,
                `author_id` INT( 10 ) NULL,
                `active` TINYINT NOT NULL DEFAULT 1,
                `display_order` INT( 10 ) NOT NULL DEFAULT 0,
                `created` DATETIME NOT NULL,
                `updated` DATETIME NOT NULL
                );

                CREATE TABLE findauthors (
                `findauthor_id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `name` varchar ( 255 ) NOT NULL,
                `active` TINYINT NOT NULL DEFAULT 1
                )
                ";

        $db->query($sql);

    }

    function dropTables(&$db) {
        $db->query("DROP TABLE IF EXISTS findposts");
    }


    function numberOfTestPosts() {
        $db = SG_DB::singleton();
        return $db->getOne('SELECT COUNT(*) FROM posts', true);
    }

    function testFindAll() {

        $count = $this->numberOfTestPosts();

        $all = FindPost::all();
        $this->assertEquals($count, $all->count(), '::all() returned the wrong number of results.');

        $expectedId = 1;
        foreach($all as $post) {
            $this->assertEquals(
                $expectedId,
                $post->id,
                '::all() returned the wrong post at position ' . $expectedId
            );
            $expectedId++;
        }

        $all = FindPost::find();
        $this->assertEquals($count, $all->count(), '::find() returned the wrong number of results.');

        $expectedId = 1;
        foreach($all as $post) {
            $this->assertEquals(
                $expectedId,
                $post->id,
                '::find() returned the wrong post at position ' . $expectedId
            );
            $expectedId++;
        }

    }

    function testGet() {

        $count = $this->numberOfTestPosts();

        for($i = 1; $i <= $count; $i++) {
            $post = FindPost::get($i);
            $this->assertTrueish($post, '::get() failed for ' . $i);
            $this->assertEquals($i, $post->id, '::get() returned the wrong result for ' . $i);
        }
    }

    function testFindByStringField() {

        $fooExpr = '/Case Test - Foo/i';

        $test = 'Find w/o array, mixed case, no explicit LIKE';
        $posts = FindPost::find('title', 'Foo');
        $this->assertCountEquals(2, $posts, $test);
        $this->assertTitlesMatch($fooExpr, $posts, $test);

        $test = 'Find w/o array, mixed case, explicit LIKE';
        $posts = FindPost::find('title LIKE', 'Foo');
        $this->assertCountEquals(2, $posts, $test);
        $this->assertTitlesMatch($fooExpr, $posts, $test);

        $test = 'Find w/ array, mixed case, no explicit LIKE';
        $posts = FindPost::find(array('title' =>'Foo'));
        $this->assertCountEquals(2, $posts, $test);
        $this->assertTitlesMatch($fooExpr, $posts, $test);

        $test = 'Find w/ array, mixed case, no explicit LIKE';
        $posts = FindPost::find(array('title LIKE' =>'Foo'));
        $this->assertCountEquals(2, $posts, $test);
        $this->assertTitlesMatch($fooExpr, $posts, $test);

    }

    function testFindByAndGetByMagicMethods() {

        $fields = array(
            'Id' => 7,
            'Slug' => 'magic-method-test-post',
            'Title' => 'Magic Method Test Post',
            'Body' => 'Magic Method Test Post Body',
            'Author' => 1,
            'Active' => 0,
            'DisplayOrder' => 42,
            // Hard to test, also, would you ever do findByCreated()?
            // It would be more like findNewerThan(x) findOlderThan(x)
            //'Created',
            //'Updated'
        );

        foreach($field as $f => $v) {

            $findMethod = "findBy$f";
            $posts = FindPost::$findMethod();
            $this->assertCountEquals(1, $posts);

            $col = underscore($f);
            $value = is_numeric($v) ? $v : "'$v'";

            $this->assertSqlEquals(
                "SELECT * FROM findposts WHERE `$col` = $value",
                $posts,
                "column '$col' using method '$method'"
            );

            $getMethod = "getBy$f";
            $post = FindPost::$getMethod($v);
            $this->assertTrueish($post, "getBy$f did not return anything.");
            $this->assertEquals(7, $post->id, "getBy$f got the wrong post.");
        }

    }

    function testFindByAndGetByMagicMethodsWithName() {

        $posts = FindPost::findByAuthor('* Hinz');

        $this->assertSqlEquals(
            "SELECT * FROM findposts, findauthors WHERE findauthors.findauthor_id = findposts.author_id AND findauthors.name LIKE '% Hinz'",
            $posts
        );

        $this->assertCountEquals(2, $posts);

        foreach($posts as $post) {
            $this->assertEquals("Matt Hinz", $post->author->name);
        }


        $post = FindPost::getByAuthor("* Hinz");
        $this->assertTrueish($post, "getByAuthor did not return anything");
        $this->assertEquals("Matt Hinz", $post->author->name, "wrong author on post returned by getByAuthor");
    }

    function testWhereActiveMagicMethods() {

        $activeCount = $this->numberOfTestPosts('active = 1');
        $this->assertEquals(
            $activeCount,
            FindPost::all()->whereActive()->count(),
            'wrong # of active posts'
        );

        $inactiveCount = $this->numberOfTestPosts('active = 0');
        $this->assertEquals(
            $inactiveCount,
            FindPost::all()->whereNotActive()->count(),
            'wrong # of inactive posts'
        );
    }

    function testFluentWhereMethods() {

        $posts = FindPost::all();

        $this->assertSqlEquals(
            "SELECT * FROM findposts",
            $posts
        );

        $filteredPosts = $posts->where(array('title' => 'test'));
        $this->assertNotSame($posts, $filteredPosts, 'where() should return a new result set instance');
        $posts = $filteredPosts;

        $this->assertSqlEquals(
            "SELECT * FROM `findposts` WHERE (`title` LIKE 'test')",
            $posts
        );

        $posts = $posts->or('body', '*foo*');
        $this->assertSqlEquals(
            "SELECT * FROM `findposts` WHERE (`title` LIKE 'test') OR (`body` LIKE '%foo%')",
            $posts
        );

        $posts = $posts->and('created <', '2008-01-01');
        $this->assertSqlEquals(
            "SELECT * FROM `findposts` WHERE (`title` LIKE 'test') OR (`body` LIKE '%foo%') AND (`created` < '2008-01-01')",
            $post
        );

    }

    function testOrderBy() {

        $allPosts = FindPost::all();

        $test = 'single string, no direction marker';
        $posts = $allPosts->orderBy('title');

        foreach(array('', 'ASC', 'DESC') as $marker) {

            $test = "single string, $marker";

            $posts = $allPosts->orderBy("title $marker");
            $this->assertNotSame($posts, $allPosts, 'orderBy should return a new result set instance');
            $this->assertSqlEquals("SELECT * FROM `findposts` ORDER BY `title` $marker", $posts, $test);

            if ($marker) {
                $test = "single item array, $marker";
                $posts = $allPosts->orderBy(array('title' => $marker));
                $this->assertSqlEquals("SELECT * FROM `findposts` ORDER BY `title` $marker", $posts, $test);
            }

        }

        $test = 'multiple strings, varying direction markers';
        $posts = $allPosts->orderBy('title', 'created asc', 'updated desc');
        $this->assertSqlEquals("SELECT * FROM `findposts` ORDER BY `title` ASC, `created` ASC, `updated` DESC", $posts, $test);

        $test = 'multi-item array, varying direction markers';
        $posts = $allPosts->orderBy(array('title', 'created' => 'asc', 'updated' => 'desc'));
        $this->assertSqlEquals("SELECT * FROM `findposts` ORDER BY `title` ASC, `created` ASC, `updated` DESC", $posts, $test);
    }

    function testDetectRegex() {

        $flags = array(
            '' => 'REGEXP BINARY',
            'i' => 'REGEXP'
        );

        foreach($flags as $flag => $operator) {

            $posts = FindPost::all()->where(array('title' => '/[a-z]\d+\s*\(in parens\)/' . $flag));
            $this->assertSqlEquals(
                "SELECT * FROM `findposts` WHERE `title` $operator '[a-z]\\d+\\s*[(]in parens[)]'",
                $posts
            );
        }

    }

    function testComplexArrayCriteria() {

        $posts = FindPost::all()->where(array(

            'title' => 'foo',
            array('created <' => '2008-01-01', 'OR', 'updated >' => '2009-01-01'),
            'OR',
            array(
                array('title' => 'bar', 'OR', 'title' => 'baz')
            )

        ));

        $this->assertSqlEquals(
            "
            SELECT
                *
            FROM `findposts`
            WHERE
                (
                    (`title` LIKE 'foo')
                    AND
                    (
                        `created` < '2008-01-01'
                        OR
                        `updated` > '2009-01-01'
                    )
                    OR
                    (
                        (
                            `title` LIKE 'bar'
                            OR
                            `title` LIKE 'baz'
                        )
                    )
                )
            ",
            $posts
        );


    }

    function testCustomOperators() {

        $operators = array('=', '!=', 'LIKE', 'NOT LIKE', '<', '<=', '>', '>=');
        foreach($operators as $op) {

            $posts = FindPost::all()->where(array("title $op" => 'foo'));
            $this->assertSqlEquals(
                "SELECT * FROM `findposts` WHERE (`title` $op 'foo')",
                $posts,
                "Operator: $op"
            );

        }

    }

    function testNotCriteria() {

        $posts = FindPost::all()->where(array('title NOT' => 'foo'));
        $this->assertSqlEquals("SELECT * FROM `findposts` WHERE (NOT (`title` LIKE 'foo'))", $posts, 'NOT');

        $posts = FindPost::all()->where(array('display_order NOT' => 42));
        $this->assertSqlEquals("SELECT * FROM `findposts` WHERE (NOT (`title` = 'foo'))", $posts, 'NOT');
    }

    function testInCriteria() {

        $ids = array(1,2,3,4);
        $idSql = '(' . implode(',', $ids) . ')';

        $posts = FindPost::all()->where('findpost_id', $ids);
        $this->assertSqlEquals("SELECT * FROM `findposts` WHERE (`findpost_id` IN $idSql)", $posts, 'implicit IN');

        $posts = FindPost::all()->where('findpost_id IN', $ids);
        $this->assertSqlEquals("SELECT * FROM `findposts` WHERE (`findpost_id` IN $idSql)", $posts, 'explicit IN');

        $posts = FindPost::all()->where('findpost_id NOT IN', $ids);
        $this->assertSqlEquals("SELECT * FROM `findposts` WHERE (`findpost_id` NOT IN $idSql)", $posts, 'NOT IN');
    }

    function testRelatedCriteria() {

        $posts = FindPost::all()->where('author.name', 'Matt Hinz');
        $this->assertSqlEquals(
            "SELECT * FROM `findposts`, `findauthors` WHERE (`findauthors`.`findauthor_id` = `findpost`.`author_id`) AND `findauthor`.`name` LIKE 'Matt Hinz'",
            $posts
        );

    }

    function assertTrueish($condition, $message = null) {
        $this->assertTrue($condition == true, $message);
    }

    function assertSqlEquals($expected, $actual, $extraMessage = null) {

        $this->assertTrueish($actual);

        if (!is_string($actual)) {
            $actual = $actual->getSql();
        }

        $this->assertEquals(
            normalize_sql($expected),
            normalize_sql($actual),
            'SQL differs. ' . ($extraMessage ? "($extraMessage)" : '')
        );

    }

    function assertCountEquals($count, $posts, $extraMessage = null) {

        $this->assertEquals(
            $count,
            $posts->count(),
            'Wrong number of posts. ' . ($extraMessage ? "($extraMessage)" : '')
        );

    }

    function assertTitlesMatch($expr, $posts, $extraMessage = null) {

        foreach($posts as $post) {

            $postMessage =
                "Post {$post->id} does not match expression: '{$post->title}'" .
                $extraMessage ? "($extraMessage)" : '';

            $this->assertTrueish(
                preg_match($expr, $post->title),
                $message
            );

        }

    }

}


?>
