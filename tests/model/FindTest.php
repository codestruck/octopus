<?php

require_once(dirname(dirname(__FILE__)) . '/Octopus_DB_TestCase.php');

class FindAuthor extends Octopus_Model {

    protected $fields = array(
        'name' => array('type' => 'string'),
        'posts' => array(
            'type' => 'hasMany',
            'model' => 'FindPost'
        ),
        'active'
    );

}

class FindCategory extends Octopus_Model {

    protected $fields = array(
        'name',
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
        'category' => array(
            'type' => 'manyToMany',
            'model' => 'FindCategory'
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

        $sql = "
                CREATE TABLE find_categories (
                    `find_category_id` INT(10) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    `name` varchar(255) NOT NULL
                )
        ";
        $db->query($sql);

        $sql = "

                CREATE TABLE find_category_find_post_join (
                    `find_post_id` int not null,
                    `find_category_id` int not null
                )

        ";
        $db->query($sql);

    }

    function dropTables(&$db) {
        $db->query("DROP TABLE IF EXISTS find_category_find_post_join");
        $db->query("DROP TABLE IF EXISTS find_posts");
        $db->query("DROP TABLE IF EXISTS find_authors");
        $db->query("DROP TABLE IF EXISTS find_categories");
    }

    function testParentheses() {

        $criteria = array(
            'title' => 'foo',
            array('NOT' => array('title' => 'bar', 'active' => 0)),
            'OR',
            'title', 'baz'
        );

        $expectedWhereClause = <<<END

        (
            (`find_posts`.`title` LIKE 'foo')
            AND
            (NOT ((`find_posts`.`title` LIKE 'bar') AND (`find_posts`.`active` = '0')))
        )
        OR
        (`find_posts`.`title` LIKE 'baz')

END;

        $this->assertSqlEquals(
            "SELECT * FROM find_posts WHERE $expectedWhereClause",
            FindPost::all()->where($criteria)
        );

    }

    function testDelete() {
        $all = FindPost::all();

        $this->assertEquals(6, count($all));
        $all->delete();
        $this->assertEquals(0, count($all));
    }

    function testDeleteChain() {
        $all = FindPost::all();

        $this->assertEquals(6, count($all));
        $this->assertEquals(0, count($all->delete()));
    }

    function testOr() {

        $posts = FindPost::all()->whereActive();
        $this->assertSqlEquals("SELECT * FROM find_posts WHERE `find_posts`.`active` = '1'", $posts);

        $posts = $posts->where(array('title' => 'foo'), 'OR', 'title', 'bar', 'OR', 'slug', 'foobar');
        $this->assertSqlEquals(
            "
                SELECT * FROM find_posts WHERE
                    (`find_posts`.`active` = '1')
                    AND
                        (
                            (
                                (`find_posts`.`title` LIKE 'foo')
                                OR
                                (`find_posts`.`title` LIKE 'bar')
                            )
                            OR
                            (`find_posts`.`slug` = 'foobar')
                        )
            ",
            $posts
        );

    }

    function testHasOneCriteria() {

        $posts = FindPost::all();
        $posts = $posts->where('author.name', '%Hinz');

        $this->assertEquals(3, count($posts));

        $ids = array();

        foreach($posts as $p) {

            $this->assertFalse(isset($ids[$p->id]), "id {$p->id} already seen");
            $this->assertTrue(!!$p->author, "{$p->id} has no author");
            $this->assertEquals("Matt Hinz", $p->author->name);

        }

    }

    function testMultipleHasOneCriteria() {

        $mattPosts = FindPost::find('author.name', '%Hinz');
        $mikePosts = FindPost::find('author.name', '%Estes');

        $this->assertEquals(3, count($mattPosts));
        $this->assertEquals(2, count($mikePosts));

        $posts = $mattPosts->add($mikePosts);
        $this->assertEquals(5, count($posts));

        foreach($posts as $post) {
            $this->assertTrue(!!$post->author, 'author not set on post ' . $post->id);
        }

    }

    function testAddQuery() {

        $expectedSql =
            "
            SELECT
                *
            FROM
                find_posts
            WHERE
                (
                    (`find_posts`.`active` = '1')
                    AND
                    (`find_posts`.`title` LIKE 'foo')
                )
                OR
                (
                    `find_posts`.`title` LIKE 'bar'
                )
            ";

        $posts = FindPost::all()->whereActive()->where('title', 'foo');

        $viaArray = $posts->add(array('title' => 'bar'));
        $this->assertSqlEquals($expectedSql, $viaArray, 'Failed using array args');

        $viaStringArgs = $posts->add('title', 'bar');
        $this->assertSqlEquals($expectedSql, $viaStringArgs, 'Failed using string args');

    }

    function testAddResultSet() {

        $foos = FindPost::all()->where('title', 'foo');
        $bars = FindPost::all()->where('title', 'bar');

        $posts = $foos->add($bars);

        $this->assertSqlEquals(
            "SELECT * FROM find_posts WHERE (`find_posts`.`title` LIKE 'foo') OR (`find_posts`.`title` LIKE 'bar')",
            $posts
        );
    }

    function testRemoveQuery() {

        $posts = FindPost::all()->whereActive();
        $posts = $posts->remove('title', 'foo');

        $this->assertSqlEquals(
            "SELECT * FROM find_posts WHERE (`find_posts`.`active` = '1') AND (NOT (`find_posts`.`title` LIKE 'foo'))",
            $posts
        );

    }

    function testRemoveResultSet() {

        $active = FindPost::all()->whereActive();
        $foos = FindPost::all()->where('title', 'foo');

        $posts = $active->remove($foos);

        $this->assertSqlEquals(
            "SELECT * FROM find_posts WHERE (`find_posts`.`active` = '1') AND (NOT (`find_posts`.`title` LIKE 'foo'))",
            $posts
        );

    }

    function testHasOneCount() {

        $this->markTestIncomplete();

        $authors = FindAuthor::all()->whereActive();
        $authors = $authors->where('count(posts) >', 5);

        $this->assertSqlEquals(
            "
            SELECT
                `find_authors`.*,
                COUNT(`find_posts`.*) posts_count
            FROM
                `find_authors`
            WHERE
                (`find_authors`.`active` = 1)
            HAVING
                posts_count > 5
            ",
            $authors
        );

    }

    function testOrderByHasOne() {

        $posts = FindPost::all()->orderBy('author');

        $this->assertSqlEquals("SELECT find_posts.* FROM find_posts LEFT JOIN find_authors ON `find_authors`.`find_author_id` = `find_posts`.`author_id` ORDER BY `find_authors`.`name` ASC", $posts);
    }

    function testOrderByHasOneOtherField() {

        $posts = FindPost::all()->orderBy('author.id');

        $this->assertSqlEquals(
            "SELECT find_posts.* FROM find_posts LEFT JOIN find_authors ON `find_authors`.`find_author_id` = `find_posts`.`author_id` ORDER BY `find_authors`.`find_author_id`",
            $posts
        );

    }

    function testFindAuthorDisplayField() {

        $author = new FindAuthor();
        $this->assertEquals('name', $author->getDisplayField()->getFieldName());

    }

    function testLimit() {

        $posts = FindPost::all()->where('title', 'foo');
        $this->assertSqlEquals("SELECT * FROM find_posts WHERE `find_posts`.`title` LIKE 'foo'", $posts);

        $posts = $posts->limit(10, 30);
        $this->assertSqlEquals("SELECT * FROM find_posts WHERE `find_posts`.`title` LIKE 'foo' LIMIT 10, 30", $posts);

        $posts = $posts->unlimit();
        $this->assertSqlEquals("SELECT * FROM find_posts WHERE `find_posts`.`title` LIKE 'foo'", $posts);

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

    function testGetArray() {
        $post = FindPost::get(array('slug', 'slug-for-post-1'));
        $this->assertEquals(1, $post->id);
    }

    function testGetNonExist() {
        $post = FindPost::get(99);
        $this->assertEquals(null, $post);
    }

    function testOrderByID() {

        $posts = FindPost::all()->orderBy(array('id' => 'desc'));
        $this->assertSqlEquals("SELECT * FROM find_posts ORDER BY `find_posts`.`find_post_id` DESC", $posts);

    }

    function testFindByStringField() {

        $fooExpr = '/Case Test - Foo/i';

        $test = 'Find w/o array, mixed case, no explicit LIKE';
        $posts = FindPost::find('title', '* Foo');
        $this->assertSqlEquals(
            "SELECT * FROM find_posts WHERE `find_posts`.`title` LIKE '% Foo'",
            $posts,
            $test
        );
        $this->assertCountEquals(2, $posts, $test);
        $this->assertTitlesMatch($fooExpr, $posts, $test);

        $test = 'Find w/o array, mixed case, explicit LIKE';
        $posts = FindPost::find('title LIKE', '* Foo');
        $this->assertSqlEquals(
            "SELECT * FROM find_posts WHERE `find_posts`.`title` LIKE '% Foo'",
            $posts,
            $test
        );
        $this->assertCountEquals(2, $posts, $test);
        $this->assertTitlesMatch($fooExpr, $posts, $test);

        $test = 'Find w/ array, mixed case, no explicit LIKE';
        $posts = FindPost::find(array('title' => '* Foo'));
        $this->assertSqlEquals(
            "SELECT * FROM find_posts WHERE `find_posts`.`title` LIKE '% Foo'",
            $posts,
            $test
        );
        $this->assertCountEquals(2, $posts, $test);
        $this->assertTitlesMatch($fooExpr, $posts, $test);

        $test = 'Find w/ array, mixed case, explicit LIKE';
        $posts = FindPost::find(array('title LIKE' => '* Foo'));
        $this->assertSqlEquals(
            "SELECT * FROM find_posts WHERE `find_posts`.`title` LIKE '% Foo'",
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
            "SELECT * FROM find_posts WHERE `find_posts`.`active` = '1'",
            $query
        );

        $query = FindPost::all()->whereNotActive();
        $this->assertSqlEquals(
            "SELECT * FROM find_posts WHERE `find_posts`.`active` = '0'",
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
            "SELECT * FROM find_posts WHERE `find_posts`.`title` LIKE 'test'",
            $posts,
            'simple key/value restriction w/ implicit operator'
        );

    }

    function testOrderBy() {

        $allPosts = FindPost::all();

        $test = 'single string, no direction marker';
        $posts = $allPosts->orderBy('title');

        foreach(array('', 'ASC', 'DESC') as $marker) {

            $test = "single string, $marker";

            $expectedMarker = $marker ? $marker : 'ASC';

            $posts = $allPosts->orderBy("title $marker");
            $this->assertNotSame($posts, $allPosts, 'orderBy should return a new result set instance');
            $this->assertSqlEquals("SELECT * FROM find_posts ORDER BY `find_posts`.`title` $expectedMarker", $posts, $test);

            if ($marker) {
                $test = "single item array, $marker";
                $posts = $allPosts->orderBy(array('title' => $marker));
                $this->assertSqlEquals("SELECT * FROM find_posts ORDER BY `find_posts`.`title` $expectedMarker", $posts, $test);
            }

        }

        $test = 'multiple strings, varying direction markers';
        $posts = $allPosts->orderBy('title', 'created asc', 'updated desc');
        $this->assertSqlEquals("SELECT * FROM find_posts ORDER BY `find_posts`.`title` ASC, `find_posts`.`created` ASC, `find_posts`.`updated` DESC", $posts, $test);

        $test = 'multi-item array, varying direction markers';
        $posts = $allPosts->orderBy(array('title', 'created' => 'asc', 'updated' => 'desc'));
        $this->assertSqlEquals("SELECT * FROM find_posts ORDER BY `find_posts`.`title` ASC, `find_posts`.`created` ASC, `find_posts`.`updated` DESC", $posts, $test);
    }

    function testDetectRegex() {

        $flags = array(
            '' => 'REGEXP BINARY',
            'i' => 'REGEXP'
        );

        foreach($flags as $flag => $operator) {

            $posts = FindPost::all()->where(array('title' => '/[a-z]\d+\s*\(in parens\)/' . $flag));
            $this->assertSqlEquals(
                "SELECT * FROM find_posts WHERE `find_posts`.`title` $operator '[a-z]\\d+\\s*[(]in parens[)]'",
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
                SELECT * FROM find_posts WHERE

                (
                    (`find_posts`.`title` LIKE 'foo')
                    AND
                        (
                            (`find_posts`.`created` < '2008-01-01')
                            OR
                            (`find_posts`.`updated` > '2009-01-01')
                        )
                )
                OR (
                    (`find_posts`.`title` LIKE 'bar')
                    OR
                    (`find_posts`.`title` LIKE 'baz')
                )
            ",
            $posts
        );


    }

    function testCustomOperators() {

        $operators = array('=', '!=', 'LIKE', 'LIKE', '<', '<=', '>', '>=');
        foreach($operators as $op) {

            $posts = FindPost::all()->where(array("title $op" => 'foo'));
            $this->assertSqlEquals(
                "SELECT * FROM find_posts WHERE `find_posts`.`title` $op 'foo'",
                $posts,
                "Operator: $op"
            );

            $posts = FindPost::all()->where(array("title not $op" => 'foo'));
            $this->assertSqlEquals(
                "SELECT * FROM find_posts WHERE NOT (`find_posts`.`title` $op 'foo')",
                $posts,
                "Operator: $op (NOT)"
            );


        }

    }

    function testNotCriteria() {

        $posts = FindPost::all()->where(array('title NOT' => 'foo'));
        $this->assertSqlEquals("SELECT * FROM find_posts WHERE NOT (`find_posts`.`title` LIKE 'foo')", $posts, 'NOT');

        $posts = FindPost::all()->where(array('display_order NOT' => 42));
        $this->assertSqlEquals("SELECT * FROM find_posts WHERE NOT (`find_posts`.`display_order` = '42')", $posts, 'NOT');
    }

    function testInCriteria() {

        $ids = array(1,2,3,4);
        $idSql = "('" . implode("','", $ids) . "')";

        $posts = FindPost::all()->where('Id', $ids);
        $this->assertSqlEquals("SELECT * FROM find_posts WHERE `find_posts`.`find_post_id` IN $idSql", $posts, 'implicit IN');

        $posts = FindPost::all()->where('ID IN', $ids);
        $this->assertSqlEquals("SELECT * FROM find_posts WHERE `find_posts`.`find_post_id` IN $idSql", $posts, 'explicit IN');

        $posts = FindPost::all()->where('id NOT IN', $ids);
        $this->assertSqlEquals("SELECT * FROM find_posts WHERE NOT (`find_posts`.`find_post_id` IN $idSql)", $posts, 'NOT IN');
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

        $expected = normalize_sql($expected);
        $actual = normalize_sql($actual);

        $expected = preg_replace('/\s*([\(\)])\s*/', '$1', $expected);
        $actual = preg_replace('/\s*([\(\)])\s*/', '$1', $actual);

        $expected = str_replace('WHERE(', 'WHERE (', $expected);
        $actual = str_replace('WHERE(', 'WHERE (', $actual);

        $expected = preg_replace('/(AND|OR|NOT)\(/', '$1 (', $expected);
        $actual = preg_replace('/(AND|OR|NOT)\(/', '$1 (', $actual);

                $expected = preg_replace('/\)(AND|OR|NOT)/', ') $1', $expected);
        $actual = preg_replace('/\)(AND|OR|NOT)/', ') $1', $actual);


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
