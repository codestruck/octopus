<?php

Octopus::loadClass('Octopus_DB_Migration_Runner');

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
        'website' => array(
            'type' => 'url'
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
Octopus::loadClass('Octopus_DB_Migration');

/**
 * Tests model's migration abilities
 */
class MigrationTest extends Octopus_App_TestCase {

    function setUp() {
        parent::setUp();

        $db = Octopus_DB::singleton();
        $db->query('DROP TABLE IF EXISTS _octopus_migrations');
    }

    function createMigrationFile($index, $name) {

        global $__migration_testcase;
        $__migration_testcase = $this;

        $className = $name;
        if (!ends_with($className, 'Migration')) {
            $className .= 'Migration';
        }

        $migrationDir = $this->getMigrationDir();
        @mkdir($migrationDir);

        $file = $migrationDir . $index . '_' . $name . '.php';
        file_put_contents(
            $file,
            <<<END
<?php

class $className extends Octopus_DB_Migration {

    public function up() {

        global \$__test_migrations_run;
        global \$__migration_testcase;

        \$__test_migrations_run[] = __METHOD__;

        // Verify that all DB classes are available
        \$__migration_testcase->assertTrue(class_exists('Octopus_DB_Schema'), 'Octopus_DB_Schema exists');
        \$__migration_testcase->assertTrue(class_exists('Octopus_DB_Schema_Reader'), 'Octopus_DB_Schema_Reader exists');
        \$__migration_testcase->assertTrue(class_exists('Octopus_DB_Schema_Writer'), 'Octopus_DB_Schema_Writer exists');
        \$__migration_testcase->assertTrue(class_exists('Octopus_DB_Select'), 'Octopus_DB_Select exists');
        \$__migration_testcase->assertTrue(class_exists('Octopus_DB_Insert'), 'Octopus_DB_Insert exists');
        \$__migration_testcase->assertTrue(class_exists('Octopus_DB_Update'), 'Octopus_DB_Update exists');
        \$__migration_testcase->assertTrue(class_exists('Octopus_DB_Delete'), 'Octopus_DB_Delete exists');

    }

    public function down() {
        global \$__test_migrations_run;
        \$__test_migrations_run[] = __METHOD__;
    }

}

?>
END

        );

        return $file;

    }

    function getMigrationDir() {
        return $this->siteDir . '/migrations/';
    }

    function testNoMigrationsAppliedInitially() {

        $db = Octopus_DB::singleton();
        $db->query('DROP TABLE IF EXISTS _octopus_migrations');

        $runner = new Octopus_DB_Migration_Runner($this->getMigrationDir());
        $this->assertEquals(array(), $runner->getAppliedMigrations());

    }

    function testApplyMigrationCreatesRecord() {

        $file = $this->createMigrationFile('001', 'FirstMigration');

        $runner = new Octopus_DB_Migration_Runner($this->getMigrationDir());
        $runner->migrate();

        $s = new Octopus_DB_Select();
        $s->table('_octopus_migrations');

        $this->assertEquals(
            array(
                array(
                    'hash' => '28de66ebcd542332a2e23273ef229a3f487ff703',
                    'set' => 'octopus/tests/.working/MigrationTest-sitedir/migrations',
                    'name' => 'firstmigration',
                    'number' => 1,
                    'file' => $file
                )
            ),
            $s->fetchAll()
        );

    }

    function testUnapplyMigrationDeletesRecord() {

        $file = $this->createMigrationFile('001', 'UnapplyDeletesRecord');

        $runner = new Octopus_DB_Migration_Runner($this->getMigrationDir());

        $runner->migrate();
        $s = new Octopus_DB_Select();
        $s->table('_octopus_migrations');
        $this->assertEquals(1, count($s->fetchAll()), 'should be 1 record in migrations table');

        $runner->migrate(0);

        $s = new Octopus_DB_Select();
        $s->table('_octopus_migrations');
        $this->assertEquals(0, count($s->fetchAll()), 'should be no records in migrations table');

    }

    function testDoubleUnderscoreSortsFirst() {

        $first = $this->createMigrationFile('001', 'FirstMigration');
        $second = $this->createMigrationFile('__999999', 'ActuallyFirstMigration');

        $runner = new Octopus_DB_Migration_Runner(dirname($first));
        $migrations = $runner->getMigrations();

        $this->assertEquals(2, count($migrations));
        $this->assertTrue(($item = array_shift($migrations)) instanceof ActuallyFirstMigration, 'Wrong class: ' . get_class($item));
        $this->assertTrue(($item = array_shift($migrations)) instanceof FirstMigration, 'Wrong class: ' . get_class($item));

    }

    function testMigrateBetweenTwoVersionsUp() {

        $first = $this->createMigrationFile('001', 'FirstMigration');
        $second = $this->createMigrationFile('002', 'SecondMigration');
        $third = $this->createMigrationFile('003', 'ThirdMigration');

        $runner = new Octopus_DB_Migration_Runner(dirname($first));

        $migrations = $runner->getMigrations(3, 2);

        $this->assertEquals(2, count($migrations), '# of migrations');
        $this->assertTrue(array_shift($migrations) instanceof SecondMigration, 'type of 1st mig');
        $this->assertTrue(array_shift($migrations) instanceof ThirdMigration, 'type of 2nd mig');

    }

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

    function testModelMigrate() {

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

            'website' => array(
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
