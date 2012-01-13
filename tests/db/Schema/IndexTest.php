<?php

/**
 * @group DB
 * @group schema
 * @group schema_index
 */
class Octopus_DB_Schema_IndexTest extends PHPUnit_Framework_TestCase {

    function __construct() {
        $this->db = Octopus_DB::singleton();
    }

    function testDropUniqueMultiKey()
    {

        $this->db->query('DROP TABLE IF EXISTS translation_values', true);

        $d = new Octopus_DB_Schema();
        $t = $d->newTable('translation_values');
        $t->newTextSmall('value');
        $t->newIndex('UNIQUE', null, array('value', 'lang'));
        $t->newTextSmall('lang');
        $t->create();

        $d = new Octopus_DB_Schema();
        $t = $d->newTable('translation_values');
        $t->newTextSmall('value');
        $t->newTextSmall('lang');
        $sql = $t->toSql();
        $t->create();

        $reader = new Octopus_DB_Schema_Reader('translation_values');
        $fields = $reader->getFields();
        $indexes = $reader->getIndexes();

        $this->assertEquals(0, count($indexes));

    }

    function testSwitchUniqueKey()
    {
    	return $this->markTestIncomplete("This test fails consistently on Hinz's laptop (PHP 5.3.6, MySQL 5.5.10)");

        $this->db->query('DROP TABLE IF EXISTS translation_values');

        $d = new Octopus_DB_Schema();
        $t = $d->newTable('translation_values');
        $t->newKey('keyword_id', true);
        $t->newPrimaryKey('keyword_id');
        $t->newTextSmall('value');
        $t->newIndex('UNIQUE', 'value');
        $t->create();

        $reader = new Octopus_DB_Schema_Reader('translation_values');
        $indexes = $reader->getIndexes();

        $this->assertEquals('value', $indexes[1]['Key_name']);
        $this->assertEquals('value', $indexes[1]['Column_name']);
        $this->assertEquals(0, $indexes[1]['Non_unique']);

        $d = new Octopus_DB_Schema();
        $t = $d->newTable('translation_values');
        $t->newKey('keyword_id', true);
        $t->newPrimaryKey('keyword_id');
        $t->newTextSmall('value');
        $t->newIndex('value');

        $sql = $t->toSql();
        $t->create();

        $reader = new Octopus_DB_Schema_Reader('translation_values');
        $indexes = $reader->getIndexes();
        // dump_r($indexes);

        $this->assertEquals('value', $indexes[1]['Key_name']);
        $this->assertEquals('value', $indexes[1]['Column_name']);
        $this->assertEquals(1, $indexes[1]['Non_unique']);

        $expected = <<<END
ALTER TABLE `translation_values`
CHANGE `keyword_id` keyword_id int(10) NOT NULL, DROP PRIMARY KEY,
DROP COLUMN `value`,
CHANGE `lang` lang varchar(250) NOT NULL, DROP PRIMARY KEY

END;
        // $this->assertEquals($expected, $sql);

    }

}
