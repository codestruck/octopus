<?php

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class MigrationTest extends Octopus_App_TestCase {

    function setUp() {
        parent::setUp();

        $db = Octopus_DB::singleton();
        $db->query('DROP TABLE IF EXISTS _migrations');
    }

    function createMigrationFile($index, $name, $failOn = null) {

        global $__migration_testcase;
        $__migration_testcase = $this;

        $className = $name;
        if (!ends_with($className, 'Migration')) {
            $className .= 'Migration';
        }

        $migrationDir = $this->getMigrationDir();
        @mkdir($migrationDir);

        $upExtra = (strpos($failOn, 'up') !== false) ? '$__migration_testcase->assertTrue(false, "up called when it should not be");' : '';
        $downExtra = (strpos($failOn, 'down') !== false) ? '$__migration_testcase->assertTrue(false, "down called when it should not be");' : '';

        $file = $migrationDir . $index . '_' . $name . '.php';
        file_put_contents(
            $file,
            <<<END
<?php

class $className extends Octopus_DB_Migration {

    public function up(\$db, \$schema) {

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

        \$__migration_testcase->assertTrue(\$db instanceof Octopus_DB, 'first arg is DB');
        \$__migration_testcase->assertTrue(\$schema instanceof Octopus_DB_Schema, 'second arg is schema');

        $upExtra

    }

    public function down(\$db, \$schema) {
        global \$__test_migrations_run;
        \$__test_migrations_run[] = __METHOD__;

        $downExtra
    }

}

?>
END

        );

        return $file;

    }

    function getMigrationDir() {
        return $this->getSiteDir() . '/migrations/';
    }

    function testNoMigrationsAppliedInitially() {

        $db = Octopus_DB::singleton();
        $db->query('DROP TABLE IF EXISTS _migrations');

        $runner = new Octopus_DB_Migration_Runner($this->getMigrationDir());
        $this->assertEquals(array(), $runner->getAppliedMigrations());

    }

    function testApplyMigrationCreatesRecord() {

        $file = $this->createMigrationFile('001', 'FirstCreatesRecord');

        $runner = new Octopus_DB_Migration_Runner($this->getMigrationDir());
        $runner->migrate();

        $s = new Octopus_DB_Select();
        $s->table('_migrations');

        $this->assertEquals(
            array(
                array(
                    'hash' => '2d5d5a937a7f476aecc728bfc050b9d15ee4183b',
                    'name' => 'firstcreatesrecord',
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
        $s->table('_migrations');
        $this->assertEquals(1, count($s->fetchAll()), 'should be 1 record in migrations table');

        $runner->migrate(0);

        $s = new Octopus_DB_Select();
        $s->table('_migrations');
        $this->assertEquals(0, count($s->fetchAll()), 'should be no records in migrations table');

    }

    function testHaveUnappliedMigrations() {

        $first = $this->createMigrationFile('001', 'FirstHaveUnapplied');

        $runner = new Octopus_DB_Migration_Runner(dirname($first));
        $this->assertTrue($runner->haveUnappliedMigrations(), 'should have unapplied migrations w/ none applied');
        $runner->migrate();
        $this->assertFalse($runner->haveUnappliedMigrations(), 'should not have unapplied migrations after migrate()');

        $second = $this->createMigrationFile('002', 'SecondHaveUnapplied');
        $this->assertTrue($runner->haveUnappliedMigrations(), 'should have unapplied migrations after creating a new one');
        $runner->migrate();
        $this->assertFalse($runner->haveUnappliedMigrations(), 'should not have unapplied migrations after migrate()');
    }

    function testUnderscoresIncreasePriority() {

        $third = $this->createMigrationFile('111', 'BBB');
        $second = $this->createMigrationFile('_222', 'AAA');
        $first = $this->createMigrationFile('__333', 'CCC');

        $runner = new Octopus_DB_Migration_Runner(dirname($first));
        $versions = $runner->getMigrationVersions();

        $expected = array('CCC', 'AAA', 'BBB');
        $this->assertEquals(count($expected), count($versions), '# of versions');

        while($expected) {
            $v = array_shift($versions);
            $this->assertEquals(strtolower(array_shift($expected)), strtolower($v['name']));
        }

    }

    function testUnderscoreSortsFirst() {

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

        $migrations = $runner->getMigrations(2, 3);

        $this->assertEquals(3, count($migrations), '# of migrations');
        $this->assertTrue(array_shift($migrations) instanceof FirstMigration, 'type of 1st mig');
        $this->assertTrue(array_shift($migrations) instanceof SecondMigration, 'type of 2nd mig');
        $this->assertTrue(array_shift($migrations) instanceof ThirdMigration, 'type of 3rd mig');

    }

    function testMigrateBetweenTwoVersionsDown() {

        $first = $this->createMigrationFile('001', 'FirstDown');
        $second = $this->createMigrationFile('002', 'SecondDown');
        $third = $this->createMigrationFile('003', 'ThirdDown');

        $runner = new Octopus_DB_Migration_Runner(dirname($first));

        $migrations = $runner->getMigrations(3, 2);

        $this->assertEquals(2, count($migrations), '# of migrations');
        $this->assertTrue(array_shift($migrations) instanceof ThirdDownMigration, 'type of 3rd mig');
        $this->assertTrue(array_shift($migrations) instanceof SecondDownMigration, 'type of 2nd mig');


    }

    function testApplyNewUnappliedMigrationsEarlierThanCurrentScope() {

        $first = $this->createMigrationFile('001', 'FirstMigration');
        $second = $this->createMigrationFile('002', 'SecondMigration');

        $runner = new Octopus_DB_Migration_Runner(dirname($first));
        $GLOBALS['__test_migrations_run'] = array();
        $runner->migrate();

        $this->assertMigrationsApplied($runner, 'FirstMigration', 'SecondMigration');

        $third = $this->createMigrationFile('003', 'ThirdMigration');
        $sneaky = $this->createMigrationFile('__001', 'SneakyMigration');

        $GLOBALS['__test_migrations_run'] = array();
        $runner->migrate();
        $run = $GLOBALS['__test_migrations_run'];

        $this->assertEquals(2, count($run), '# of migrations run');

        $this->assertMigrationsApplied($runner, 'SneakyMigration', 'FirstMigration', 'SecondMigration', 'ThirdMigration');
    }

    function assertMigrationsApplied($runner /*, $class1, $class2 */) {

        $applied = $runner->getAppliedMigrations();
        $classes = func_get_args();
        array_shift($classes);

        $this->assertEquals(count($classes), count($applied), '# of migrations applied');

        while(!empty($applied)) {

            $version = array_shift($applied);
            $class = array_shift($classes);

            $this->assertTrue(strcasecmp($version['name'], $class) == 0, "{$version['name']} == $class");
        }
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
                'index' => 'PRIMARY'
            ),

            'name' => array(
                'type' => 'varchar',
                'size' => '250',
                'options' => 'NOT NULL',
                'index' => ''
            ),

            'name_short' => array(
                'type' => 'varchar',
                'size' => '50',
                'options' => 'NOT NULL',
                'index' => ''
            ),

            'name_short_size' => array(
                'type' => 'varchar',
                'size' => '50',
                'options' => 'NOT NULL',
                'index' => ''
            ),

            'name_long' => array(
                'type' => 'text',
                'size' => '',
                'options' => 'NOT NULL',
                'index' => ''
            ),

            'name_really_long' => array(
                'type' => 'text',
                'size' => '',
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
                'index' => 'INDEX'
            ),

            'birth_date' => array(
                'type' => 'datetime',
                'size' => '',
                'options' => 'NOT NULL',
                'index' => ''
            ),

            'just_date' => array(
                'type' => 'date',
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
                'index' => 'PRIMARY'
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
                'index' => 'INDEX'
            )
        );
        $this->assertColsMatch($expectedCols, 'migrate_test_dogs');

        $expectedCols = array(
            'migrate_test_category_id' => array(
                'type' => 'int',
                'size' => 10,
                'options' => 'NOT NULL AUTO_INCREMENT',
                'index' => 'PRIMARY'
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
            'migrate_test_person_id' => array(
                'type' => 'int',
                'size' => 10,
                'options' => 'NOT NULL',
                'index' => 'INDEX'
            ),
            'migrate_test_category_id' => array(
                'type' => 'int',
                'size' => 10,
                'options' => 'NOT NULL',
                'index' => 'INDEX'
            )
        );
        $this->assertColsMatch($expectedCols, 'migrate_test_category_migrate_test_person_join');
    }

    function assertColsMatch($expectedCols, $table) {

        $schema = new Octopus_DB_Schema();
        $this->assertTrue($schema->checkTable($table), "Table `$table` does not exist");

        $table = new Octopus_DB_Schema_Reader($table);
        $cols = $table->getFields();

        foreach($cols as $id => $col) {

            if (!isset($expectedCols[$id])) {
                $this->assertTrue(false, "Unexpected column `$id` found");
            }

            if (empty($expectedCols[$id])) {
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

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class MigrateTestPerson extends Octopus_Model {

    protected $fields = array(
        'name',
        'name_short' => array('type' => 'text', 'length' => 50),
        'name_short_size' => array('type' => 'text', 'size' => 50),
        'name_long' => array('type' => 'text', 'size' => 260),
        'name_really_long' => array('type' => 'text', 'length' => PHP_INT_MAX),
        'age' => array('type' => 'number'),
        'birth_date' => array( 'type' => 'datetime'),
        'just_date' => array('type' => 'date'),
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

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class MigrateTestDog extends Octopus_Model {
    protected $fields = array(
        'name',
        'person' => array('type' => 'hasOne', 'model' => 'MigrateTestPerson')
    );
}

/**
 * @internal
 * @copyright (c) 2012 Codestruck, LLC.
 * @license http://opensource.org/licenses/mit-license.php/
 */
class MigrateTestCategory extends Octopus_Model {
    protected $fields = array(
        'name'
    );
}
