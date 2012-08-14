<?php

/**
 * @group DB
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_DB_Select_Test extends PHPUnit_Framework_TestCase
{


    function testNumRowsOneTable() {

        $s = new Octopus_DB_Select();
        $s->table('test');
        $s->where('foo = 1');

        $this->assertEquals('SELECT COUNT(*) FROM test WHERE foo = 1', $s->createCountSelect()->getSql());

    }

    function testNumRowsInnerJoin() {

        $s = new Octopus_DB_Select();
        $s->table('test');
        $s->innerJoin('other_table', 'test_id', array('field1', 'field2'));
        $s->where('field1 = 2');

        $this->assertEquals('SELECT COUNT(*) FROM test INNER JOIN other_table USING (test_id) WHERE field1 = 2', $s->createCountSelect()->getSql());

    }

    function testNumRowsLeftJoin() {

        $s = new Octopus_DB_Select();
        $s->table('test');
        $s->leftJoin('other_table', 'test_id', array('field1', 'field2'));
        $s->where('field1 = 2');

        $this->assertEquals('SELECT COUNT(*) FROM test LEFT JOIN other_table USING (test_id) WHERE field1 = 2', $s->createCountSelect()->getSql());

    }


    function testNumRowsRightJoin() {

        $s = new Octopus_DB_Select();
        $s->table('test');
        $s->rightJoin('other_table', 'test_id', array('field1', 'field2'));
        $s->where('field1 = 2');

        $this->assertEquals('SELECT COUNT(*) FROM test RIGHT JOIN other_table USING (test_id) WHERE field1 = 2', $s->createCountSelect()->getSql());

    }

    function testGetSql()
    {
        $select = new Octopus_DB_Select();
        $select->table('test');
        $sql = $select->getSql();

        $this->assertEquals('SELECT * FROM test', $sql);
    }

    function testCompatInto()
    {
        $select = new Octopus_DB_Select();
        $select->table('test');
        $sql = $select->getSql();

        $this->assertEquals('SELECT * FROM test', $sql);
    }

    function testCompatTablle()
    {
        $select = new Octopus_DB_Select();
        $select->table('test');
        $sql = $select->getSql();

        $this->assertEquals('SELECT * FROM test', $sql);
    }

    function testDistinct() {

        $s = new Octopus_DB_Select();
        $s->table('foo', array('what', 'hay'));
        $s->distinct('foo', 'what');

        $this->assertEquals($s->getSql(), 'SELECT DISTINCT(what), hay FROM foo');
    }

    function testDistinct2() {

        $s = new Octopus_DB_Select();
        $s->table('foo', array('what', 'hay'));
        $s->table('bar', array('a', 'b'));
        $s->distinct('foo', 'what');

        $this->assertEquals($s->getSql(), 'SELECT DISTINCT(foo.what), foo.hay, bar.a, bar.b FROM foo, bar');
    }

    function testOmitFields() {

        $s = new Octopus_DB_Select();
        $s->table('foo', array('what', 'hay'));
        $s->table('bar', array());
        $s->table('baz');

        $this->assertEquals($s->getSql(), 'SELECT foo.what, foo.hay, baz.* FROM foo, bar, baz');
    }

    function testFieldsString()
    {

        $s = new Octopus_DB_Select();
        $s->table('foo', 'what');
        $s->table('bar', array());
        $s->table('baz');

        $sql = 'SELECT foo.what, baz.* FROM foo, bar, baz';
        $this->assertEquals($sql, $s->getSql());
    }

    function testGroup() {

        $s = new Octopus_DB_Select();
        $s->table('abcdef', array('b', 'c'));
        $s->where("b = ?", 123);
        $s->groupBy('b');

        $this->assertEquals($s->getSql(), "SELECT b, c FROM abcdef WHERE b = ? GROUP BY b");
    }

    function testGroupHaving() {

        $s = new Octopus_DB_Select();
        $s->table('abcdef', array('b', 'c'));
        $s->where("b = ?", 123);
        $s->groupBy('b');
        $s->having('COUNT(*) > 2');

        $this->assertEquals($s->getSql(), "SELECT b, c FROM abcdef WHERE b = ? GROUP BY b HAVING COUNT(*) > 2");
    }

    function testGroupHavingArg() {

        $s = new Octopus_DB_Select();
        $s->table('abcdef', array('b', 'c'));
        $s->where("b = ?", 123);
        $s->groupBy('b');
        $s->having('COUNT(*) > ?', 2);

        $this->assertEquals($s->getSql(), "SELECT b, c FROM abcdef WHERE b = ? GROUP BY b HAVING COUNT(*) > ?");
    }

    function testOrder() {

        $s = new Octopus_DB_Select();
        $s->table('abcdef', array('b', 'c'));
        $s->where("b = ?", 123);
        $s->orderBy('b');

        $this->assertEquals($s->getSql(), "SELECT b, c FROM abcdef WHERE b = ? ORDER BY b");
    }

    function testOrder2() {

        $s = new Octopus_DB_Select();
        $s->table('abcdef', array('b', 'c'));
        $s->table('fake', array());
        $s->where("b = ?", '123');
        $s->orderBy('b ASC');

        $this->assertEquals($s->getSql(), "SELECT abcdef.b, abcdef.c FROM abcdef, fake WHERE b = ? ORDER BY b ASC");

    }

    function testOrder3() {

        $s = new Octopus_DB_Select();
        $s->table('abcdef', array('b', 'c'));
        $s->where("b = ?", 123);
        $s->orderBy('b');
        $s->orderBy('a ASC');

        $this->assertEquals($s->getSql(), "SELECT b, c FROM abcdef WHERE b = ? ORDER BY b, a ASC");
    }

    function testLimit() {

        $s = new Octopus_DB_Select();
        $s->table('abcdef', array('b', 'c'));
        $s->where("b = ?", 123);
        $s->limit(1);

        $this->assertEquals($s->getSql(), "SELECT b, c FROM abcdef WHERE b = ? LIMIT 1");
    }

    function testLimit2() {

        $s = new Octopus_DB_Select();
        $s->table('abcdef', array('b', 'c'));
        $s->where("b = ?", 123);
        $s->orderBy('b');
        $s->orderBy('a ASC');
        $s->limit(5, 5);

        $this->assertEquals($s->getSql(), "SELECT b, c FROM abcdef WHERE b = ? ORDER BY b, a ASC LIMIT 5, 5");
    }

    function testLimitZeroOffset() {

        $s = new Octopus_DB_Select();
        $s->table('abcdef', array('b', 'c'));
        $s->where("b = ?", 123);
        $s->limit(0, 20);

        $this->assertEquals($s->getSql(), "SELECT b, c FROM abcdef WHERE b = ? LIMIT 0, 20");
    }

    function testLeftJoin()
    {
        $s = new Octopus_DB_Select();
        $s->table('a');
        $s->leftJoin('b', array('a.foo_id', 'b.foo_id'));
        $s->where('a.what = ?', 'foo');

        $sql = "SELECT a.*, b.* FROM a LEFT JOIN b ON a.foo_id = b.foo_id WHERE a.what = ?";

        $this->assertEquals($sql, $s->getSql());

        $s = new Octopus_DB_Select();
        $s->table('a');
        $s->leftJoin('b', 'foo_id');
        $s->where('a.what = ?', 'foo');

        $sql = "SELECT a.*, b.* FROM a LEFT JOIN b USING (foo_id) WHERE a.what = ?";

        $this->assertEquals($sql, $s->getSql());

        $s = new Octopus_DB_Select();
        $s->table('a');
        $s->leftJoin(array('bar', 'b'), 'foo_id', array('foo_name'));
        $s->where('a.what = ?', 'foo');

        $sql = "SELECT a.*, b.foo_name FROM a LEFT JOIN bar AS b USING (foo_id) WHERE a.what = ?";

        $this->assertEquals($sql, $s->getSql());

        $s = new Octopus_DB_Select();
        $s->table('a');
        $s->leftJoin(array('bar', 'b'), array('foo_id'), array());
        $s->where('a.what = ?', 'foo');

        $sql = "SELECT a.* FROM a LEFT JOIN bar AS b USING (foo_id) WHERE a.what = ?";

        $this->assertEquals($sql, $s->getSql());

        // multi join

        $s = new Octopus_DB_Select();
        $s->table('a');
        $s->leftJoin('b', 'foo_id');
        $s->leftJoin('c', array('b.bar_id', 'c.bar_id'));
        $s->where('a.what = ?', 'foo');

        $sql = "SELECT a.*, b.*, c.* FROM a LEFT JOIN b USING (foo_id) LEFT JOIN c ON b.bar_id = c.bar_id WHERE a.what = ?";

        $this->assertEquals($sql, $s->getSql());

        // join fields

        $s = new Octopus_DB_Select();
        $s->table('a', array('a_field'));
        $s->leftJoin('b', array('a.foo_id', 'b.foo_id'), array('b_field', 'b_id'));
        $s->where('a.what = ?', 'foo');

        $sql = "SELECT a.a_field, b.b_field, b.b_id FROM a LEFT JOIN b ON a.foo_id = b.foo_id WHERE a.what = ?";

        $this->assertEquals($sql, $s->getSql());

        $s = new Octopus_DB_Select();
        $s->table(array('bananas', 'b'));
        $s->leftJoin(array('apples', 'a'), 'apple_id', array('apple_name'));

        $sql = "SELECT b.*, a.apple_name FROM bananas AS b LEFT JOIN apples AS a USING (apple_id)";

        $this->assertEquals($sql, $s->getSql());

    }

    function testRightJoin()
    {
        $s = new Octopus_DB_Select();
        $s->table('a');
        $s->rightJoin('b', array('a.foo_id', 'b.foo_id'));
        $s->where('a.what = ?', 'foo');

        $sql = "SELECT a.*, b.* FROM a RIGHT JOIN b ON a.foo_id = b.foo_id WHERE a.what = ?";

        $this->assertEquals($sql, $s->getSql());

        $s = new Octopus_DB_Select();
        $s->table('a');
        $s->rightJoin('b', 'foo_id');
        $s->where('a.what = ?', 'foo');

        $sql = "SELECT a.*, b.* FROM a RIGHT JOIN b USING (foo_id) WHERE a.what = ?";

        $this->assertEquals($sql, $s->getSql());

        // multi join

        $s = new Octopus_DB_Select();
        $s->table('a');
        $s->rightJoin('b', 'foo_id');
        $s->leftJoin('c', array('b.bar_id', 'c.bar_id'));
        $s->where('a.what = ?', 'foo');

        $sql = "SELECT a.*, b.*, c.* FROM a RIGHT JOIN b USING (foo_id) LEFT JOIN c ON b.bar_id = c.bar_id WHERE a.what = ?";

        $this->assertEquals($sql, $s->getSql());

    }

    function testInnerJoin()
    {
        $s = new Octopus_DB_Select();
        $s->table('a');
        $s->innerJoin('b', array('a.foo_id', 'b.foo_id'));
        $s->where('a.what = ?', 'foo');

        $sql = "SELECT a.*, b.* FROM a INNER JOIN b ON a.foo_id = b.foo_id WHERE a.what = ?";

        $this->assertEquals($sql, $s->getSql());

        $s = new Octopus_DB_Select();
        $s->table('a');
        $s->innerJoin('b', 'foo_id');
        $s->where('a.what = ?', 'foo');

        $sql = "SELECT a.*, b.* FROM a INNER JOIN b USING (foo_id) WHERE a.what = ?";

        $this->assertEquals($sql, $s->getSql());

        // multi join

        $s = new Octopus_DB_Select();
        $s->table('a');
        $s->rightJoin('b', 'foo_id');
        $s->innerJoin('c', array('b.bar_id', 'c.bar_id'));
        $s->where('a.what = ?', 'foo');

        $sql = "SELECT a.*, b.*, c.* FROM a RIGHT JOIN b USING (foo_id) INNER JOIN c ON b.bar_id = c.bar_id WHERE a.what = ?";

        $this->assertEquals($sql, $s->getSql());

    }

    function testTableAlias()
    {

        $s = new Octopus_DB_Select();
        $s->table(array('accessories', 'a'));
        $s->where('a.what = ?', 'foo');

        $sql = "SELECT * FROM accessories AS a WHERE a.what = ?";

        $this->assertEquals($sql, $s->getSql());

        // test array, no alias
        $s = new Octopus_DB_Select();
        $s->table(array('accessories'));
        $s->where('what = ?', 'foo');

        $sql = "SELECT * FROM accessories WHERE what = ?";

        $this->assertEquals($sql, $s->getSql());

        $s = new Octopus_DB_Select();
        $s->table(array('accessories', 'a'));
        $s->table(array('bananas', 'b'), array('color', 'size'));
        $s->where('a.what = ?', 'foo');
        $s->where('b.color = ?', 'baz');

        $sql = "SELECT a.*, b.color, b.size FROM accessories AS a, bananas AS b WHERE a.what = ? AND b.color = ?";

        $this->assertEquals($sql, $s->getSql());

        $s = new Octopus_DB_Select();
        $s->table(array('accessories', 'a'));
        $s->rightJoin(array('bananas', 'b'), 'foo_id');
        $s->innerJoin(array('category', 'c'), array('b.bar_id', 'c.bar_id'));
        $s->where('a.what = ?', 'foo');

        $sql = "SELECT a.*, b.*, c.* FROM accessories AS a RIGHT JOIN bananas AS b USING (foo_id) INNER JOIN category AS c ON b.bar_id = c.bar_id WHERE a.what = ?";

        $this->assertEquals($sql, $s->getSql());

    }

    function testJoinFields()
    {
        $s = new Octopus_DB_Select();
        $s->table(array('accessories', 'a'), array('abc', 'cba'));
        $s->leftJoin(array('cat', 'c'), array('c.cat_id', 'a.cat_id'), array());

        $sql = "SELECT a.abc, a.cba FROM accessories AS a LEFT JOIN cat AS c ON c.cat_id = a.cat_id";

        $this->assertEquals($sql, $s->getSql());
    }

    function testFunctionCountOneTableOneField()
    {
        $s = new Octopus_DB_Select();
        $s->table('test', array('id'));
        $s->runFunction('COUNT', 'test', 'id');
        $sql = "SELECT COUNT(id) FROM test";

        $this->assertEquals($sql, $s->getSql());
    }

    function testFunctionCountOneTableOneFieldAliased()
    {
        $s = new Octopus_DB_Select();
        $s->table('test', array('id'));
        $s->runFunction('COUNT', 'test', array('id', 'count'));
        $sql = "SELECT COUNT(id) AS count FROM test";

        $this->assertEquals($sql, $s->getSql());
    }

    function testFunctionCountOneTableTwoFields()
    {
        $s = new Octopus_DB_Select();
        $s->table('test', array('active', 'id'));
        $s->runFunction('COUNT', 'test', array('id', 'count'));
        $sql = "SELECT active, COUNT(id) AS count FROM test";

        $this->assertEquals($sql, $s->getSql());
    }

    function testFunctionCountTwoTables()
    {
        $s = new Octopus_DB_Select();
        $s->table('test', array('id', 'active'));
        $s->table('bar', array('a', 'b'));
        $s->runFunction('COUNT', 'test', array('id', 'count'));
        $sql = "SELECT COUNT(test.id) AS count, test.active, bar.a, bar.b FROM test, bar";

        $this->assertEquals($sql, $s->getSql());
    }

}

?>
