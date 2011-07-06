<?php

class MigrateTestPerson extends Octopus_Model {

    protected $fields = array(
        'name',
        'age' => array('type' => 'number'),
        'birth_date' => array( 'type' => 'datetime'),
        'dogs' => array(
            'type' => 'hasMany',
            'model' => 'MigrateTestDog'
        ),
        'favorite_dog' => array(
            'type' => 'has_one',
            'model' => 'MigrateTestDog',
        ),
        'bio' => array('type' => 'html'),
        'categories' => array(
            'type' => 'many_to_many',
            'model' => 'MigrateTestCategory'
        ),
        'net_worth' => array(
            'type' => 'numeric',
            'decimal_places' => 2,
            'precision' => 4
        ),
        'slug',
        'order',
        'dummy' => array('type' => 'virtual'),
        'created',
        'updated',
        'active'
    );

}

class MigrateTestDog extends Octopus_Model {
    protected $fields = array(
        'name',
        'person' => array('type' => 'hasOne', 'model' => 'MigrateTestPerson')
    );
}

class MigrateTestCategory extends Octopus_Model {
    protected $fields = array(
        'name'
    );
}

Octopus::loadClass('Octopus_DB_Schema');
Octopus::loadClass('Octopus_DB_Schema_Model');
Octopus::loadClass('Octopus_DB_Schema_Reader');

/**
 * Tests model's migration abilities
 */
class MigrationTest extends PHPUnit_Framework_TestCase {

    function testAllFieldTypesCovered() {

        $fieldsDir = OCTOPUS_DIR . 'includes/classes/Model/Field/';

        $allFieldTypes = array();
        foreach(glob($fieldsDir . '*.php') as $f) {
            $field = basename($f, '.php');
            $allFieldTypes[$field] = true;
        }

        $obj = new MigrateTestPerson();
        foreach($obj->getFields() as $field) {
            $type = get_class($field);
            $type = preg_replace('/^Octopus_Model_Field_/', '', $type);
            unset($allFieldTypes[$type]);
        }

        foreach($allFieldTypes as $type => $unused) {
            $this->assertEquals('', $type, "Octopus_Model_Field_$type does not have migration test coverage");
        }

    }

    function testMigrate() {

        $db = Octopus_DB::singleton();
        $db->query('DROP TABLE IF EXISTS migrate_test_persons');
        $db->query('DROP TABLE IF EXISTS migrate_test_dogs');
        $db->query('DROP TABLE IF EXISTS migrate_test_categories');

        Octopus_DB_Schema_Model::makeTable('MigrateTestPerson');
        Octopus_DB_Schema_Model::makeTable('MigrateTestDog');
        Octopus_DB_Schema_Model::makeTable('MigrateTestCategory');

        $expectedCols = array(

            'migrate_test_person_id' => array(
                'type' => 'int',
                'size' => '10',
                'options' => 'NOT NULL AUTO_INCREMENT',
                'index' => 'PRIMARY KEY'
            ),

            'name' => array(
                'type' => 'varchar',
                'size' => '250',
                'options' => 'NOT NULL',
                'index' => ''
            ),

            'age' => array(
                'type' => 'bigint',
                'size' => 20,
                'options' => 'NOT NULL',
                'index' => ''
            ),

            'favorite_dog_id' => array(
                'type' => 'int',
                'size' => 10,
                'options' => 'NOT NULL',
                'index' => 'MUL'
            ),

            'birth_date' => array(
                'type' => 'datetime',
                'size' => '',
                'options' => 'NOT NULL',
                'index' => ''
            ),

            'bio' => array(
                'type' => 'text',
                'size' => '',
                'options' => 'NOT NULL',
                'index' => ''
            ),

            'net_worth' => array(
                'type' => 'decimal',
                'size' => '4,2',
                'options' => 'NOT NULL',
                'index' => ''
            ),

            'slug' => array(
                'type' => 'varchar',
                'size' => 250,
                'options' => 'NOT NULL',
                'index' => ''
            ),

            'order' => array(
                'type' => 'int',
                'size' => 11,
                'options' => 'NOT NULL',
                'index' => ''
            ),

            'created' => array(
                'type' => 'datetime',
                'size' => '',
                'options' => 'NOT NULL',
                'index' => ''
            ),

            'updated' => array(
                'type' => 'datetime',
                'size' => '',
                'options' => 'NOT NULL',
                'index' => ''
            ),

            'active' => array(
                'type' => 'tinyint',
                'size' => 1,
                'options' => 'NOT NULL',
                'index' => ''
            )


        );
        $this->assertColsMatch($expectedCols, 'migrate_test_persons');

        $expectedCols = array(
            'migrate_test_dog_id' => array(
                'type' => 'int',
                'size' => 10,
                'options' => 'NOT NULL AUTO_INCREMENT',
                'index' => 'PRIMARY KEY'
            ),
            'name' => array(
                'type' => 'varchar',
                'size' => 250,
                'options' => 'NOT NULL',
                'index' => ''
            ),
            'person_id' => array(
                'type' => 'int',
                'size' => 10,
                'options' => 'NOT NULL',
                'index' => 'MUL'
            )
        );
        $this->assertColsMatch($expectedCols, 'migrate_test_dogs');

        $expectedCols = array(
            'migrate_test_category_id' => array(
                'type' => 'int',
                'size' => 10,
                'options' => 'NOT NULL AUTO_INCREMENT',
                'index' => 'PRIMARY KEY'
            ),
            'name' => array(
                'type' => 'varchar',
                'size' => 250,
                'options' => 'NOT NULL',
                'index' => ''
            )

        );
        $this->assertColsMatch($expectedCols, 'migrate_test_categories');

        $expectedCols = array(
            'category_id' => array(
                'type' => 'int',
                'size' => 10,
                'options' => 'NOT NULL',
                'index' => 'MUL'
            ),
            'migrate_test_person_id' => array(
                'type' => 'int',
                'size' => 10,
                'options' => 'NOT NULL',
                'index' => 'MUL'
            )
        );
        $this->assertColsMatch($expectedCols, 'category_migrate_test_person_join');
    }

    function assertColsMatch($expectedCols, $table) {

        $schema = new Octopus_DB_Schema();
        $this->assertTrue($schema->checkTable($table), "Table `$table` does not exist");

        $table = new Octopus_DB_Schema_Reader($table);
        $cols = $table->getFields();

        foreach($cols as $id => $col) {

            if (!isset($expectedCols[$id])) {
                dump_r($col);
                $this->assertTrue(false, "Unexpected column `$id` found");
            }

            if (empty($expectedCols[$id])) {
                dump_r($col);
                $this->assertTrue(false, "Lazy programmer needs to fill in details for `$id`");
            }

            foreach($expectedCols[$id] as $key => $expectedValue) {

                $this->assertTrue(isset($col[$key]), "$key not set for `$id`");
                $this->assertEquals($expectedValue, $col[$key], "$key is wrong for `$id`");
                unset($expectedCols[$id]);
            }

        }
        $this->assertTrue(empty($expectedCols), 'Missing columns: ' . (implode(', ', array_keys($expectedCols))));

    }

}

?>
