<?php

class Octopus_DB_Schema_Model {

    public static function makeTable($modelClass) {

        $schema = new Octopus_DB_Schema();

        if ($modelClass instanceof Octopus_Model) {
            $model = $modelClass;
            $modelClass = get_class($model);
        } else {

            $modelClass = camel_case($modelClass, true);
            $model = new $modelClass();
        }

        foreach($model->getFields() as $field) {
            $field->beforeMigrate($schema);
        }

        $table = $schema->newTable(to_table_name($modelClass));
        $table->newKey(to_id($modelClass), true);
        $table->newPrimaryKey(to_id($modelClass));

        foreach($model->getFields() as $field) {
            $field->migrate($schema, $table);
            $field->migrateIndexes($schema, $table);
        }

        foreach ($model->getIndexes() as $index) {
            if (is_array($index)) {
                $table->newIndex('INDEX', implode('_', $index), $index);
            } else {
                $table->newIndex($index);
            }
        }

        $table->create();

        foreach($model->getFields() as $field) {
            $field->afterMigrate($schema);
        }

    }

}

