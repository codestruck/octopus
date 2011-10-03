<?php

/**
 * @group schema
 * @group DB
 */
class Octopus_DB_Schema_Writer_Test extends PHPUnit_Framework_TestCase
{

    function __construct() {
        $this->db = Octopus_DB::singleton();
    }

    function setUp() {
        $sql = "DROP table IF EXISTS test";
        $this->db->query($sql);
    }

    function testCreateSimple()
    {
        $w = new Octopus_DB_Schema_Writer('test');
        $w->newKey('test_id');
        $w->newTextSmall('test_name');
        $sql = $w->toSql();

        $test = "CREATE TABLE `test` (
`test_id` int(10) NOT NULL,
`test_name` varchar(250) NOT NULL
);";


        $this->assertEquals($test, $sql);

    }

    function testCreateSimpleAutoincrementKey()
    {
        $w = new Octopus_DB_Schema_Writer('test');
        $w->newKey('test_id', true);
        $w->newIndex('PRIMARY KEY', null, 'test_id');
        $w->newTextSmall('test_name');
        $sql = $w->toSql();

        $test = "CREATE TABLE `test` (
`test_id` int(10) NOT NULL AUTO_INCREMENT,
`test_name` varchar(250) NOT NULL,
PRIMARY KEY (`test_id`)
);";


        $this->assertEquals($test, $sql);

    }

    function testCreateAllTypes()
    {
        $w = new Octopus_DB_Schema_Writer('test');
        $w->newKey('test_id');
        $w->newTextSmall('test_name');
        $w->newBool('test_bool');
        $w->newGuid('test_guid');
        $w->newInt('test_int', 5);
        $w->newDate('test_date');
        $w->newDateTime('test_datetime');
        $w->newTime('test_time');
        $w->newDecimal('test_decimal', 7, 2);
        $w->newEnum('test_enum', array('a', 'b'));
        $sql = $w->toSql();

        $test = "CREATE TABLE `test` (
`test_id` int(10) NOT NULL,
`test_name` varchar(250) NOT NULL,
`test_bool` tinyint(1) NOT NULL,
`test_guid` char(36) NOT NULL,
`test_int` int(5) NOT NULL,
`test_date` date NOT NULL,
`test_datetime` datetime NOT NULL,
`test_time` time NOT NULL,
`test_decimal` decimal(7,2) NOT NULL,
`test_enum` enum('a','b') NOT NULL default 'a'
);";


        $this->assertEquals($test, $sql);

    }




    function testAlterSimple()
    {

        $create = "CREATE TABLE `test` (
`test_id` int(10) NOT NULL ,
`test_name` varchar(250) NOT NULL
);";

        $this->db->query($create);


        $w = new Octopus_DB_Schema_Writer('test');
        $w->newKey('test_id');
        $w->newTextSmall('test_name');
        $sql = $w->toSql();

        $test = '';

        $this->assertEquals($test, $sql);

    }

    function testAlterSimpleAutoincrementKey()
    {
        $create = "CREATE TABLE `test` (
`test_id` int(10) NOT NULL AUTO_INCREMENT,
`test_name` varchar(250) NOT NULL,
PRIMARY KEY  (`test_id`)
);";

        $this->db->query($create);

        $w = new Octopus_DB_Schema_Writer('test');
        $w->newKey('test_id', true);
        $w->newIndex('PRIMARY KEY', null, 'test_id');
        $w->newTextSmall('test_name');
        $sql = $w->toSql();

        $test = '';

        $this->assertEquals($test, $sql);

    }

    function testAlterNewField()
    {

        $create = "CREATE TABLE `test` (
`test_id` int(10) NOT NULL
);";

        $this->db->query($create);

        $w = new Octopus_DB_Schema_Writer('test');
        $w->newKey('test_id');
        $w->newTextSmall('test_name');
        $sql = $w->toSql();

        $test = "ALTER TABLE `test`
ADD COLUMN `test_name` varchar(250) NOT NULL\n";

        $this->assertEquals($test, $sql);

        $w->create();

        $reader = new Octopus_DB_Schema_Reader('test');
        $fields = $reader->getFields();
        $this->assertArrayHasKey('test_id', $fields);
        $this->assertArrayHasKey('test_name', $fields);

    }

    function testAlterDropField()
    {

        $create = "CREATE TABLE `test` (
`test_id` int(10) NOT NULL ,
`test_name` varchar(250) NOT NULL
);";

        $this->db->query($create);

        $w = new Octopus_DB_Schema_Writer('test');
        $w->newKey('test_id');
        $sql = $w->toSql();

        $test = "ALTER TABLE `test`
DROP COLUMN `test_name`\n";

        $this->assertEquals($test, $sql);

        $w->create();

        $reader = new Octopus_DB_Schema_Reader('test');
        $fields = $reader->getFields();
        $this->assertArrayHasKey('test_id', $fields);
        $this->assertArrayNotHasKey('test_name', $fields);


    }

    function testAlterAddAndDropField()
    {

        $create = "CREATE TABLE `test` (
`test_id` int(10) NOT NULL ,
`test_name` varchar(250) NOT NULL
);";

        $this->db->query($create);

        $w = new Octopus_DB_Schema_Writer('test');
        $w->newKey('test_id');
        $w->newTextSmall('test_other');
        $sql = $w->toSql();

        $test = "ALTER TABLE `test`
DROP COLUMN `test_name`,
ADD COLUMN `test_other` varchar(250) NOT NULL\n";

        $this->assertEquals($test, $sql);

        $w->create();

        $reader = new Octopus_DB_Schema_Reader('test');
        $fields = $reader->getFields();
        $this->assertArrayHasKey('test_id', $fields);
        $this->assertArrayHasKey('test_other', $fields);
        $this->assertArrayNotHasKey('test_name', $fields);

    }

    function testAlterChangeField()
    {

        $create = "CREATE TABLE `test` (
`test_id` int(10) NOT NULL ,
`test_name` varchar(250) NOT NULL
);";

        $this->db->query($create);

        $w = new Octopus_DB_Schema_Writer('test');
        $w->newKey('test_id');
        $w->newTextLarge('test_name');
        $sql = $w->toSql();

        $test = "ALTER TABLE `test`
CHANGE `test_name` `test_name` text NOT NULL\n";

        $this->assertEquals($test, $sql);

        $w->create();

        $reader = new Octopus_DB_Schema_Reader('test');
        $fields = $reader->getFields();
        $this->assertArrayHasKey('test_id', $fields);
        $this->assertArrayHasKey('test_name', $fields);

    }

    function testAlterAddPrimaryKey()
    {

        $create = "CREATE TABLE `test` (
`test_id` int(10) NOT NULL,
`test_name` varchar(250) NOT NULL
);";

        $this->db->query($create);

        $w = new Octopus_DB_Schema_Writer('test');
        $w->newKey('test_id', true);
        $w->newIndex('PRIMARY KEY', null, 'test_id');
        $w->newTextSmall('test_name');
        $sql = $w->toSql();

        $test = "ALTER TABLE `test`
CHANGE `test_id` `test_id` int(10) NOT NULL AUTO_INCREMENT,
ADD PRIMARY KEY (`test_id`)\n";

        $this->assertEquals($test, $sql);

        $w->create();

        $reader = new Octopus_DB_Schema_Reader('test');
        $fields = $reader->getFields();
        $this->assertArrayHasKey('test_id', $fields);
        $this->assertArrayHasKey('test_name', $fields);
        $this->assertEquals('NOT NULL AUTO_INCREMENT', $fields['test_id']['options']);
        $this->assertEquals('PRIMARY KEY', $fields['test_id']['index']);

    }

    function testAlterDropPrimaryKey()
    {

        $create = "CREATE TABLE `test` (
`test_id` int(10) NOT NULL AUTO_INCREMENT,
`test_name` varchar(250) NOT NULL,
PRIMARY KEY (`test_id`)
);";

        $this->db->query($create);

        $w = new Octopus_DB_Schema_Writer('test');
        $w->newKey('test_id');
        $w->newTextSmall('test_name');
        $sql = $w->toSql();

        $test = "ALTER TABLE `test`
CHANGE `test_id` `test_id` int(10) NOT NULL,
DROP PRIMARY KEY\n";

        $this->assertEquals($test, $sql);
        $w->create();

        $reader = new Octopus_DB_Schema_Reader('test');
        $fields = $reader->getFields();
        $this->assertArrayHasKey('test_id', $fields);
        $this->assertArrayHasKey('test_name', $fields);
        $this->assertEquals('NOT NULL', $fields['test_id']['options']);
        $this->assertEquals('', $fields['test_id']['index']);

    }

    function testAlterNewPrimaryKeyField()
    {

        $create = "CREATE TABLE `test` (
`test_name` varchar(250) NOT NULL
);";

        $this->db->query($create);

        $w = new Octopus_DB_Schema_Writer('test');
        $w->newKey('test_id', true);
        $w->newindex('PRIMARY KEY', null, 'test_id');
        $w->newTextSmall('test_name');
        $sql = $w->toSql();

        $test = "ALTER TABLE `test`
ADD COLUMN `test_id` int(10) NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`test_id`)\n";

        $this->assertEquals($test, $sql);

        $w->create();

        $reader = new Octopus_DB_Schema_Reader('test');
        $fields = $reader->getFields();
        $this->assertArrayHasKey('test_id', $fields);
        $this->assertArrayHasKey('test_name', $fields);
        $this->assertEquals('NOT NULL AUTO_INCREMENT', $fields['test_id']['options']);
        $this->assertEquals('PRIMARY KEY', $fields['test_id']['index']);

    }

    function testAlterDropPrimaryKeyField()
    {

        $create = "CREATE TABLE `test` (
`test_id` int(10) NOT NULL AUTO_INCREMENT,
`test_name` varchar(250) NOT NULL,
PRIMARY KEY (`test_id`)
);";

        $this->db->query($create);

        $w = new Octopus_DB_Schema_Writer('test');
        $w->newTextSmall('test_name');
        $sql = $w->toSql();

        $test = "ALTER TABLE `test`
DROP COLUMN `test_id`\n";

        $this->assertEquals($test, $sql);

        $w->create();

        $reader = new Octopus_DB_Schema_Reader('test');
        $fields = $reader->getFields();
        $this->assertArrayNotHasKey('test_id', $fields);
        $this->assertArrayHasKey('test_name', $fields);

    }

    function testCreateUniqueMultiKey()
    {

        $this->db->query('DROP TABLE IF EXISTS translation_values');

        $d = new Octopus_DB_Schema();
        $t = $d->newTable('translation_values');
        $t->newKey('keyword_id');
        $t->newIndex('UNIQUE', null, array('keyword_id', 'lang'));
        $t->newTextSmall('lang');
        $sql = $t->toSql();

        $expected = <<<END
CREATE TABLE `translation_values` (
`keyword_id` int(10) NOT NULL,
`lang` varchar(250) NOT NULL,
UNIQUE (`keyword_id`,`lang`)
);
END;
        $this->assertEquals($expected, $sql);

    }

    function testAlterUniqueMultiKey()
    {

        $this->db->query('DROP TABLE IF EXISTS translation_values');

        $d = new Octopus_DB_Schema();
        $t = $d->newTable('translation_values');
        $t->newKey('keyword_id');
        $t->newTextSmall('value');
//        $t->newIndex('UNIQUE', null, array('keyword_id', 'lang'));
        $t->newTextSmall('lang');
        $t->create();

        $d = new Octopus_DB_Schema();
        $t = $d->newTable('translation_values');
        $t->newKey('keyword_id');
        $t->newIndex('UNIQUE', null, array('keyword_id', 'lang'));
        $t->newTextSmall('lang');
        $sql = $t->toSql();

        $reader = new Octopus_DB_Schema_Reader('translation_values');

        $expected = <<<END
ALTER TABLE `translation_values`
ADD UNIQUE (`keyword_id`,`lang`),
DROP COLUMN `value`

END;
        $this->assertEquals($expected, $sql);

    }

    function testPrimaryKey()
    {

        $this->db->query('DROP TABLE IF EXISTS prim_test');

        $d = new Octopus_DB_Schema();

        $t = $d->newTable('prim_test');
        $t->newKey('id', true);
        $t->newPrimaryKey('id');
        $sql = $t->toSql();

        $expected = <<<END
CREATE TABLE `prim_test` (
`id` int(10) NOT NULL AUTO_INCREMENT,
PRIMARY KEY (`id`)
);
END;
        $this->assertEquals($expected, $sql);

    }

    function testIndexShort()
    {

        $this->db->query('DROP TABLE IF EXISTS prim_test');

        $d = new Octopus_DB_Schema();

        $t = $d->newTable('prim_test');
        $t->newKey('id', true);
        $t->newIndex('INDEX', 'id');
        $sql = $t->toSql();

        $expected = <<<END
CREATE TABLE `prim_test` (
`id` int(10) NOT NULL AUTO_INCREMENT,
INDEX (`id`)
);
END;
        $this->assertEquals($expected, $sql);

    }

    function testIndexVeryShort()
    {

        $this->db->query('DROP TABLE IF EXISTS prim_test');

        $d = new Octopus_DB_Schema();

        $t = $d->newTable('prim_test');
        $t->newKey('id', true);
        $t->newIndex('id');
        $sql = $t->toSql();

        $expected = <<<END
CREATE TABLE `prim_test` (
`id` int(10) NOT NULL AUTO_INCREMENT,
INDEX (`id`)
);
END;
        $this->assertEquals($expected, $sql);

    }

}
