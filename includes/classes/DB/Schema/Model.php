<?php

Octopus::loadClass('Octopus_DB_Schema');

class Octopus_DB_Schema_Model {

    public static function makeTable($modelClass) {

        $schema = new Octopus_DB_Schema();

        if ($modelClass instanceof Octopus_Model) {
            $model = $modelClass;
            $modelClass = get_class($model);
        } else {

            $modelClass = camel_case($modelClass, true);
            Octopus::loadModel($modelClass);

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
        }

        $table->create();

        foreach($model->getFields() as $field) {
            $field->afterMigrate($schema);
        }

    }

}

