<?php

require_once(dirname(dirname(__FILE__)) . '/Octopus_DB_TestCase.php');

class FindAuthor extends Octopus_Model {

    protected $fields = array(
        'name' => array('type' => 'string')
    );

}

class FindPost extends Octopus_Model {

    protected $fields = array(
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
            'model' => 'FindAuthor',
            'type' => 'hasOne'
        ),
        'active' => array(
            'type' => 'boolean',
        ),
        'display_order' => array(
            'type' => 'order',
        ),
        'created',
        'updated',

    );


    public static function &create($row) {

        $obj = new FindPost();

        return $obj;

    }


}

/**
 * @group find
 * @group Model
 */
class FindTest extends Octopus_DB_TestCase {

    function __construct() {
        parent::__construct('model/find-data.xml');
    }

    function createTables(&$db) {

        $sql = "CREATE TABLE find_posts (
                `find_post_id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `title` varchar ( 255 ) NOT NULL,
                `slug` varchar ( 255 ) NOT NULL,
                `body` text NOT NULL,
                `author_id` INT( 10 ) NULL,
                `active` TINYINT NOT NULL DEFAULT 1,
                `display_order` INT( 10 ) NOT NULL DEFAULT 0,
                `created` DATETIME NOT NULL,
                `updated` DATETIME NOT NULL
                );
                ";

        $db->query($sql);

        $sql = "
                CREATE TABLE find_authors (
                `find_author_id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `name` varchar ( 255 ) NOT NULL,
                `active` TINYINT NOT NULL DEFAULT 1
                )
                ";

        $db->query($sql);

    }

    function dropTables(&$db) {
        //$db->query("DROP TABLE IF EXISTS find_posts");
        //$db->query("DROP TABLE IF EXISTS findauthors");
    }

    function testOrderByHasOne() {

        $posts = FindPost::all()->orderBy('author');

        $this->assertSqlEquals("SELECT find_posts.*, find_authors_order_by_0.* FROM find_posts, find_authors AS find_authors_order_by_0 WHERE `find_authors_order_by_0`.`find_author_id` = `find_posts`.`author_id` ORDER BY `find_authors_order_by_0`.`name` ASC", $posts);
    }

    function testFindAuthorDisplayField() {

        $author = new FindAuthor();
        $this->assertEquals('name', $author->getDisplayField()->getFieldName());

    }

    function testLimit() {

        $posts = FindPost::all()->where('title', 'foo');
        $this->assertSqlEquals("SELECT * FROM find_posts WHERE (`title` LIKE 'foo')", $posts);

        $posts = $posts->limit(10, 30);
        $this->assertSqlEquals("SELECT * FROM find_posts WHERE (`title` LIKE 'foo') LIMIT 10, 30", $posts);

        $posts = $posts->unlimit();
        $this->assertSqlEquals("SELECT * FROM find_posts WHERE (`title` LIKE 'foo')", $posts);

    }


    function numberOfTestPosts($criteria = null) {
        $db = Octopus_DB::singleton();

        $sql = 'SELECT COUNT(*) FROM find_posts';

        if ($criteria) {
            $sql = "$sql WHERE $criteria";
        }

        return $db->getOne($sql, true);
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

    function testOrderByID() {

        $posts = FindPost::all()->orderBy(array('id' => 'desc'));
        $this->assertSqlEquals("SELECT * FROM find_posts ORDER BY `find_post_id` DESC", $posts);

    }

    function testFindByStringField() {

        $fooExpr = '/Case Test - Foo/i';

        $test = 'Find w/o array, mixed case, no explicit LIKE';
        $posts = FindPost::find('title', '* Foo');
        $this->assertSqlEquals(
            "SELECT * FROM find_posts WHERE (`title` LIKE '% Foo')",
            $posts,
            $test
        );
        $this->assertCountEquals(2, $posts, $test);
        $this->assertTitlesMatch($fooExpr, $posts, $test);

        $test = 'Find w/o array, mixed case, explicit LIKE';
        $posts = FindPost::find('title LIKE', '* Foo');
        $this->assertSqlEquals(
            "SELECT * FROM find_posts WHERE (`title` LIKE '% Foo')",
            $posts,
            $test
        );
        $this->assertCountEquals(2, $posts, $test);
        $this->assertTitlesMatch($fooExpr, $posts, $test);

        $test = 'Find w/ array, mixed case, no explicit LIKE';
        $posts = FindPost::find(array('title' => '* Foo'));
        $this->assertSqlEquals(
            "SELECT * FROM find_posts WHERE (`title` LIKE '% Foo')",
            $posts,
            $test
        );
        $this->assertCountEquals(2, $posts, $test);
        $this->assertTitlesMatch($fooExpr, $posts, $test);

        $test = 'Find w/ array, mixed case, explicit LIKE';
        $posts = FindPost::find(array('title LIKE' => '* Foo'));
        $this->assertSqlEquals(
            "SELECT * FROM find_posts WHERE (`title` LIKE '% Foo')",
            $posts,
            $test
        );
        $this->assertCountEquals(2, $posts, $test);
        $this->assertTitlesMatch($fooExpr, $posts, $test);
    }

/*
    function testFindByAndGetByMagicMethods() {

        $fields = array(
            // This isn't technically a field?
            //'Id' => 5,
            'Slug' => 'magic-method-test-post',
            'Title' => array('LIKE', 'Magic Method Test Post'),
            'Body' => array('LIKE', 'Magic Method Test Post Body'),
            //'Author' => 1,
            'Active' => 0,
            'DisplayOrder' => 42,
            // Hard to test, also, would you ever do findByCreated()?
            // It would be more like findNewerThan(x) findOlderThan(x)
            //'Created',
            //'Updated'
        );

        foreach($fields as $f => $v) {

            $op = '=';
            if (is_array($v)) {
                $op = $v[0];
                $v = $v[1];
            }

            $findMethod = "findBy$f";
            $posts = FindPost::$findMethod($v);


            $col = underscore($f);
            $value = is_numeric($v) ? $v : "'$v'";

            $this->assertSqlEquals(
                "SELECT * FROM find_posts WHERE (`$col` $op $value)",
                $posts,
                "column '$col' using method '$findMethod'"
            );

            $this->assertCountEquals(1, $posts);

            if ($f == 'Active' || $f == 'DisplayOrder') continue;

            $getMethod = "getBy$f";
            $post = FindPost::$getMethod($v);
            $this->assertTrueish($post, "getBy$f did not return anything.");
            $this->assertEquals(5, $post->id, "getBy$f got the wrong post.");
        }

    }
 */
    function testFindByAndGetByMagicMethodsWithName() {

        /*
        No join stuff yet!

        $posts = FindPost::findByAuthor('* Hinz');

        $this->assertSqlEquals(
            "SELECT * FROM find_posts, findauthors WHERE findauthors.findauthor_id = find_posts.author_id AND findauthors.name LIKE '% Hinz'",
            $posts
        );

        $this->assertCountEquals(2, $posts);

        foreach($posts as $post) {
            $this->assertEquals("Matt Hinz", $post->author->name);
        }


        $post = FindPost::getByAuthor("* Hinz");
        $this->assertTrueish($post, "getByAuthor did not return anything");
        $this->assertEquals("Matt Hinz", $post->author->name, "wrong author on post returned by getByAuthor");
        */
    }

    function testWhereActiveMagicMethods() {

        $query = FindPost::all()->whereActive();
        $this->assertSqlEquals(
            "SELECT * FROM find_posts WHERE (`active` = 1)",
            $query
        );

        $query = FindPost::all()->whereNotActive();
        $this->assertSqlEquals(
            "SELECT * FROM find_posts WHERE (`active` = 0)",
            $query
        );

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
            "SELECT * FROM find_posts",
            $posts
        );

        $filteredPosts = $posts->where(array('title' => 'test'));
        $this->assertNotSame($posts, $filteredPosts, 'where() should return a new result set instance');
        $posts = $filteredPosts;

        $this->assertSqlEquals(
            "SELECT * FROM find_posts WHERE (`title` LIKE 'test')",
            $posts,
            'simple key/value restriction w/ implicit operator'
        );

        $posts = $posts->or_('body', '*foo*');
        $this->assertSqlEquals(
            "SELECT * FROM find_posts WHERE (`title` LIKE 'test') OR (`body` LIKE '%foo%')",
            $posts,
            'add or'
        );

        $posts = $posts->and_('created <', '2008-01-01');
        $this->assertSqlEquals(
            "SELECT * FROM find_posts WHERE (`title` LIKE 'test') OR (`body` LIKE '%foo%') AND (`created` < '2008-01-01')",
            $posts,
            'add and'
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
            $this->assertSqlEquals("SELECT * FROM find_posts ORDER BY `title` $marker", $posts, $test);

            if ($marker) {
                $test = "single item array, $marker";
                $posts = $allPosts->orderBy(array('title' => $marker));
                $this->assertSqlEquals("SELECT * FROM find_posts ORDER BY `title` $marker", $posts, $test);
            }

        }

        $test = 'multiple strings, varying direction markers';
        $posts = $allPosts->orderBy('title', 'created asc', 'updated desc');
        $this->assertSqlEquals("SELECT * FROM find_posts ORDER BY `title` ASC, `created` ASC, `updated` DESC", $posts, $test);

        $test = 'multi-item array, varying direction markers';
        $posts = $allPosts->orderBy(array('title', 'created' => 'asc', 'updated' => 'desc'));
        $this->assertSqlEquals("SELECT * FROM find_posts ORDER BY `title` ASC, `created` ASC, `updated` DESC", $posts, $test);
    }

    function testDetectRegex() {

        $flags = array(
            '' => 'REGEXP BINARY',
            'i' => 'REGEXP'
        );

        foreach($flags as $flag => $operator) {

            $posts = FindPost::all()->where(array('title' => '/[a-z]\d+\s*\(in parens\)/' . $flag));
            $this->assertSqlEquals(
                "SELECT * FROM find_posts WHERE (`title` $operator '[a-z]\\d+\\s*[(]in parens[)]')",
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
                array('title' => 'bar', 'OR', array('title' => 'baz'))
            )

        ));

        $this->assertSqlEquals(
            "
            SELECT
                *
            FROM find_posts
            WHERE
                    (`title` LIKE 'foo')
                    AND
                    (`created` < '2008-01-01')
                    OR
                    (`updated` > '2009-01-01')
                    OR
                    (`title` LIKE 'bar')
                    OR
                    (`title` LIKE 'baz')
            ",
            $posts
        );


    }

    function testCustomOperators() {

        $operators = array('=', '!=', 'LIKE', 'LIKE', '<', '<=', '>', '>=');
        foreach($operators as $op) {

            $posts = FindPost::all()->where(array("title $op" => 'foo'));
            $this->assertSqlEquals(
                "SELECT * FROM find_posts WHERE (`title` $op 'foo')",
                $posts,
                "Operator: $op"
            );

            $posts = FindPost::all()->where(array("title not $op" => 'foo'));
            $this->assertSqlEquals(
                "SELECT * FROM find_posts WHERE (NOT (`title` $op 'foo'))",
                $posts,
                "Operator: $op (NOT)"
            );


        }

    }

    function testNotCriteria() {

        $posts = FindPost::all()->where(array('title NOT' => 'foo'));
        $this->assertSqlEquals("SELECT * FROM find_posts WHERE (NOT (`title` LIKE 'foo'))", $posts, 'NOT');

        $posts = FindPost::all()->where(array('display_order NOT' => 42));
        $this->assertSqlEquals("SELECT * FROM find_posts WHERE (NOT (`display_order` = 42))", $posts, 'NOT');
    }

    function testInCriteria() {

        $ids = array(1,2,3,4);
        $idSql = '(' . implode(',', $ids) . ')';

        $posts = FindPost::all()->where('Id', $ids);
        $this->assertSqlEquals("SELECT * FROM find_posts WHERE (`find_post_id` IN $idSql)", $posts, 'implicit IN');

        $posts = FindPost::all()->where('ID IN', $ids);
        $this->assertSqlEquals("SELECT * FROM find_posts WHERE (`find_post_id` IN $idSql)", $posts, 'explicit IN');

        $posts = FindPost::all()->where('id NOT IN', $ids);
        $this->assertSqlEquals("SELECT * FROM find_posts WHERE (NOT (`find_post_id` IN $idSql))", $posts, 'NOT IN');
    }

    function testRelatedCriteria() {

        /*
        NONE OF THIS YET

        $posts = FindPost::all()->where('author.name', 'Matt Hinz');
        $this->assertSqlEquals(
            "SELECT * FROM find_posts, `findauthors` WHERE (`findauthors`.`findauthor_id` = `findpost`.`author_id`) AND `findauthor`.`name` LIKE 'Matt Hinz'",
            $posts
        );
        */

    }

    function testResultSetForeachAll() {

        $all = FindPost::all();
        $this->assertEquals(6, count($all), 'The Count of the ::all array is wrong');

        $i = 1;
        foreach ($all as $item) {
            $this->assertEquals($i, $item->find_post_id, 'The Post Id does not match');
            $i++;
        }
        $this->assertEquals(7, $i, 'The foreach loop did not run 6 times');

    }

    function testResultSetForeachAllTwice() {

        $all = FindPost::all();
        $this->assertEquals(6, count($all));

        $i = 1;
        foreach ($all as $item) {
            $this->assertEquals($i, $item->find_post_id);
            $i++;
        }
        $this->assertEquals(7, $i);

        $this->assertEquals(6, count($all));

        $i = 1;
        foreach ($all as $item) {
            $this->assertEquals($i, $item->find_post_id);
            $i++;
        }
        $this->assertEquals(7, $i);

    }

    function testResultSetForeachKey() {

        $all = FindPost::all();
        $this->assertEquals(6, count($all), 'The Count of the ::all array is wrong');

        $i = 1;
        foreach ($all as $id => $item) {
            $this->assertEquals($i, $item->find_post_id, 'The Post Id does not match i');
            $this->assertEquals($id, $item->find_post_id, 'The Post Id does not match id');
            $i++;
        }
        $this->assertEquals(7, $i, 'The foreach loop did not run 6 times');

    }

    function testWherePrimaryKeyArray() {
        $all = FindPost::all();
        $result = $all->where(array('find_post_id' => 2));

        $this->assertEquals(1, count($result));
        $this->assertEquals('Title for Post 2', $result->first()->title);
    }

    function testWherePrimaryKeyArguments() {
        $all = FindPost::all();
        $result = $all->where('find_post_id', 2);

        $this->assertEquals(1, count($result));
        $this->assertEquals('Title for Post 2', $result->first()->title);
    }

    function assertTrueish($condition, $message = null) {
        $this->assertTrue($condition == true, $message);
    }

    function assertSqlEquals($expected, $actual, $extraMessage = null) {

        $this->assertTrueish($actual, "Null passed to assertSqlEquals" . ($extraMessage ? " ($extraMessage)" : ''));

        $params = array();
        if (!is_string($actual)) {
            $actual = $actual->getSql($params);
        }

        $this->assertEquals(
            normalize_sql($expected),
            normalize_sql($actual, $params),
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
                $postMessage
            );

        }

    }

}


?>
