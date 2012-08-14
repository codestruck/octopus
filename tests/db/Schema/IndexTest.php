<?php

/**
 * @group DB
 * @group schema
 * @group schema_index
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class Octopus_DB_Schema_IndexTest extends PHPUnit_Framework_TestCase {

    function __construct() {
        $this->db = Octopus_DB::singleton();
    }

    function testDropUniqueMultiKey()
    {

        $this->markTestSkipped();
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
        $this->markTestSkipped();

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
DROP INDEX `value`, ADD INDEX (`value`)

END;
        $this->assertEquals($expected, $sql);

    }

}
